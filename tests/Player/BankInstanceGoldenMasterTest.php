<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La banque accepte les objets INDIVIDUALISÉS (retour joueur : « dans la
 * banque je ne vois pas la durabilité »). Avant, `players_items_bank`
 * n'acceptait que des piles fongibles : un objet usé, nommé ou de
 * qualité ne pouvait pas y être déposé du tout.
 *
 * Ce qui est épinglé ici est l'INVARIANT DE LOCALISATION, la seule chose
 * qui rende la fonctionnalité sûre : un exemplaire rangé au coffre est
 * hors de portée de TOUS les gestes de jeu — il ne s'équipe pas, ne se
 * jette pas, ne compte pas dans ce que le joueur a sous la main — et il
 * revient avec son usure et son identité intactes, parce qu'il n'a
 * jamais changé de ligne.
 */
#[Group('items-golden-master')]
class BankInstanceGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function boisOrSkip(): Item
    {
        try {
            $this->link->executeQuery('SELECT location FROM players_items_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('players_items_instances.location unavailable (run migrations): ' . $e->getMessage());
        }

        return $this->itemOrSkip('bois');
    }

    /** Un exemplaire usé, donc non démotable : le cas qui motive la fonctionnalité. */
    private function wornInstance(int $playerId, Item $item): int
    {
        $instanceId = (new ItemInstanceService())->promote($playerId, $item->id);
        $this->link->executeStatement(
            'UPDATE item_instances SET wear_pending = wear_pending WHERE id = ?',
            [$instanceId]
        );
        $this->setRemainingLife($instanceId, $this->maxLifeOf($instanceId) - 3);

        return $instanceId;
    }

    public function testABankedInstanceKeepsItsWearAndComesBackIdentical(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($player->id, $bois);
        $before = ['durability' => $this->remainingLifeOf($instanceId)];

        $service->storeInBank($instanceId, $player->id);

        $bank = $service->listForBank($player->id);
        $this->assertCount(1, $bank, 'the instance is listed in the bank');
        $this->assertSame($instanceId, (int) $bank[0]['instance_id']);
        $this->assertSame(
            (int) $before['durability'],
            (int) $bank[0]['durability'],
            'la durabilité est visible en banque — tout l\'objet de la fonctionnalité'
        );

        /* La liste telle que la consomme le rendu (Ui::print_inventory
         * affiche la durabilité dès que la ligne la porte) : c'est le
         * maillon entre le service et l'écran de banque. */
        $listed = Item::get_item_list($player, bank: true);
        $instanceRows = array_filter($listed, static fn($row): bool => isset($row->instance_id));
        $this->assertCount(1, $instanceRows, 'la double lecture de la banque remonte l\'exemplaire');
        $shown = array_values($instanceRows)[0];
        $this->assertSame((int) $before['durability'], (int) $shown->durability);
        $this->assertSame(1, (int) $shown->n, 'un individu vaut une unité');
        $this->assertSame('', (string) $shown->equiped, 'jamais équipé en banque');

        $service->withdrawFromBank($instanceId, $player->id);

        $after = ['durability' => $this->remainingLifeOf($instanceId)];
        $this->assertSame($before, $after, 'ni l\'usure ni l\'identité n\'ont bougé — la ligne n\'a pas changé');
        $this->assertSame(
            [],
            array_filter(Item::get_item_list($player, bank: true), static fn($row): bool => isset($row->instance_id)),
            'et il quitte la banque au retrait'
        );
    }

    public function testABankedInstanceIsOutOfReachOfEveryGameGesture(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($player->id, $bois);
        $service->storeInBank($instanceId, $player->id);

        $this->assertSame([], $service->listForInventory($player->id, false), 'absent de l\'inventaire');
        $this->assertSame(0, $service->countInstances($player->id, $bois->id), 'ne compte pas sous la main');
        $this->assertFalse(
            $service->isInstanceEquippable($player->id, $bois->id, $instanceId),
            'non équipable tant qu\'il est au coffre'
        );
        $this->assertFalse($service->hasEquippableUnit($player->id, $bois->id), 'aucune unité équipable');
        $this->assertSame(1, $service->countOwned($player->id, $bois->id), 'mais toujours possédé');
    }

    public function testABankedInstanceCannotBeDroppedOnTheGround(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($player->id, $bois);
        $service->storeInBank($instanceId, $player->id);

        $coordsId = (int) $this->link->fetchOne(
            "SELECT id FROM coords WHERE x = 0 AND y = 0 AND z = 0 AND plan = 'gaia'"
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->dropAt($instanceId, $coordsId);
    }

    public function testAnEquippedInstanceIsRefusedAtTheCounter(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $this->link->executeStatement(
            "UPDATE players_items_instances SET equiped = 'main1' WHERE instance_id = ?",
            [$instanceId]
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->storeInBank($instanceId, $player->id);
    }

    public function testDepositingTwiceIsRefusedRatherThanSilentlyIgnored(): void
    {
        $player = $this->createRealPlayer('GmSmith');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($player->id, $bois);
        $service->storeInBank($instanceId, $player->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->storeInBank($instanceId, $player->id);
    }

    public function testAnotherPlayerCannotWithdrawFromYourVault(): void
    {
        $owner = $this->createRealPlayer('GmSmith');
        $thief = $this->createRealPlayer('GmThief');
        $bois = $this->boisOrSkip();
        $bois->add_item($owner, 1);

        $service = new ItemInstanceService();
        $instanceId = $this->wornInstance($owner->id, $bois);
        $service->storeInBank($instanceId, $owner->id);

        $this->expectException(\InvalidArgumentException::class);
        $service->withdrawFromBank($instanceId, $thief->id);
    }
}
