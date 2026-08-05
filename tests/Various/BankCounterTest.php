<?php

namespace Tests\Various;

use App\Service\BankService;
use Classes\Item;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Le coffre en banque, sur le motif du contenant (ExchangePanesView /
 * BankService) : sac d'un côté, coffre de l'autre, chaque ligne se
 * déplace — piles via players_items_bank, exemplaires via slot 'bank'.
 */
class BankCounterTest extends LegacyPlayerFixtureTestCase
{
    public function testAStackMovesBetweenBagAndVault(): void
    {
        $item = $this->itemOrSkip('bois');
        $client = $this->createRealPlayer('EpargnantHalls');

        $wood = new Item((int) $item->id);
        $wood->add_item($client, 5);

        $service = new BankService();
        $service->depositStack((int) $client->id, (int) $item->id, 5);

        $this->assertSame(5, (int) $wood->get_n($client, bank: true), 'la pile dort en banque');
        $this->assertSame(0, (int) $wood->get_n($client, includeInstances: false), 'plus rien dans le sac');

        $vault = $service->contentsOf((int) $client->id);
        $this->assertCount(1, $vault['stacks']);
        $this->assertSame(5, (int) $vault['stacks'][0]['n']);

        $service->withdrawStack((int) $client->id, (int) $item->id, 2);
        $this->assertSame(3, (int) $wood->get_n($client, bank: true));
        $this->assertSame(2, (int) $wood->get_n($client, includeInstances: false));
    }

    public function testAnExemplarMovesWithItsIdentity(): void
    {
        $item = $this->itemOrSkip('coffre_bois');
        $client = $this->createRealPlayer('CollectionneurHalls');

        $instanceId = (new \App\Service\ItemInstanceService())
            ->create((int) $client->id, (int) $item->id, (int) $client->id, '');

        $service = new BankService();
        $service->depositExemplar((int) $client->id, $instanceId);

        $vault = $service->contentsOf((int) $client->id);
        $this->assertCount(1, $vault['exemplars'], 'l\'exemplaire est au coffre');
        $this->assertSame($instanceId, (int) $vault['exemplars'][0]['instance_id']);

        $service->withdrawExemplar((int) $client->id, $instanceId);
        $this->assertSame([], $service->contentsOf((int) $client->id)['exemplars'], 'revenu au sac');
    }

    public function testQuantityGuardsRefuseInFrench(): void
    {
        $item = $this->itemOrSkip('bois');
        $client = $this->createRealPlayer('PrudentHalls');
        (new Item((int) $item->id))->add_item($client, 1);

        $service = new BankService();
        try {
            $service->depositStack((int) $client->id, (int) $item->id, 4);
            $this->fail('on ne dépose pas plus que le sac ne porte');
        } catch (\RuntimeException $e) {
            $this->assertSame('Quantité invalide.', $e->getMessage());
        }

        try {
            $service->withdrawStack((int) $client->id, (int) $item->id, 1);
            $this->fail('on ne retire pas d\'un coffre vide');
        } catch (\RuntimeException $e) {
            $this->assertSame('Quantité invalide.', $e->getMessage());
        }
    }
}
