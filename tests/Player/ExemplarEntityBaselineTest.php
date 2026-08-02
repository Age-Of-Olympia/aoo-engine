<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * An exemplar is an entity: identity only, no location yet.
 *
 * The link has to hold at the three lifecycle sites, not just in the backfill —
 * a migration alone would leave every exemplar born after it without an entity,
 * and the invariant would rot from the first crafted sword.
 *
 * Location still lives in the old tables at this step, so what these cases pin
 * is that the entity is NOWHERE: no cell, no holder. An entity that quietly
 * claimed a cell would put the same sword in two places.
 */
#[Group('items-baseline')]
class ExemplarEntityBaselineTest extends LegacyPlayerFixtureTestCase
{
    private function boisOrSkip(): Item
    {
        try {
            $this->link->executeQuery('SELECT entity_id FROM item_instances LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('item_instances.entity_id absent (run migrations): ' . $e->getMessage());
        }

        return $this->itemOrSkip('bois');
    }

    /** @return array<string, mixed> */
    private function entityOf(int $instanceId): array
    {
        $entityId = $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);
        $this->assertNotNull($entityId, 'un exemplaire sans entité est un exemplaire hors du modèle');

        $entity = $this->link->fetchAssociative('SELECT * FROM players WHERE id = ?', [(int) $entityId]);
        $this->assertNotFalse($entity, 'son entité existe vraiment');

        return $entity;
    }

    /** Promotion out of a stack gives the new exemplar its entity. */
    public function testPromotingGivesTheExemplarAnEntity(): void
    {
        $player = $this->createRealPlayer('GmExemplaireA');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 2);

        $instanceId = (new ItemInstanceService())->promote($player->id, $bois->id);
        $entity = $this->entityOf($instanceId);

        $this->assertSame(ItemInstanceService::ENTITY_TYPE, $entity['player_type']);
        $this->assertSame('bois', $entity['race'], 'son type est celui du catalogue');
        $this->assertGreaterThanOrEqual(70000000, (int) $entity['id'], 'dans la plage des objets');
        $this->assertLessThanOrEqual(79999999, (int) $entity['id']);
    }

    /** Carried, not nowhere: the holder now lives on the entity too. */
    public function testTheExemplarEntityCarriesItsHolder(): void
    {
        $player = $this->createRealPlayer('GmExemplaireB');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $instanceId = (new ItemInstanceService())->promote($player->id, $bois->id);
        $entity = $this->entityOf($instanceId);

        $this->assertNull($entity['coords_id'], 'aucune case : il est porté, pas posé');
        $this->assertSame((int) $player->id, (int) $entity['holder_id'], 'son porteur est sur l\'entité');
        $this->assertSame('', $entity['slot'], 'en main, dans aucun emplacement');

        // Both halves are written while the readers move over.
        $this->assertSame(
            $player->id,
            $this->holderOfInstance($instanceId),
            'et le porteur est bien celui-là'
        );
    }

    /** A crafted exemplar's entity wears the name given at the forge. */
    public function testACraftedExemplarKeepsItsNameOnItsEntity(): void
    {
        $player = $this->createRealPlayer('GmExemplaireC');
        $bois = $this->boisOrSkip();

        $instanceId = (new ItemInstanceService())->create($player->id, $bois->id, $player->id, 'Éclat de Dorna');
        $entity = $this->entityOf($instanceId);

        $this->assertSame('Éclat de Dorna', $entity['name']);
        $this->assertSame('bois', $entity['race'], 'le nom personnalisé ne change pas son type');
    }

    /** Demotion takes the entity with the exemplar: no orphan left behind. */
    public function testDemotingRemovesTheEntityToo(): void
    {
        $player = $this->createRealPlayer('GmExemplaireD');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 1);

        $service = new ItemInstanceService();
        $instanceId = $service->promote($player->id, $bois->id);
        $entityId = (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);

        $this->assertTrue($service->demote($instanceId), 'un exemplaire vierge redevient une pile');

        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM item_instances WHERE id = ?', [$instanceId]),
            'l\'exemplaire est parti'
        );
        $this->assertFalse(
            $this->link->fetchOne('SELECT 1 FROM players WHERE id = ?', [$entityId]),
            'son entité aussi — sinon elles s\'accumulent à chaque aller-retour'
        );
    }

    /** Two exemplars never share an entity. */
    public function testTwoExemplarsGetTwoEntities(): void
    {
        $player = $this->createRealPlayer('GmExemplaireE');
        $bois = $this->boisOrSkip();
        $bois->add_item($player, 2);

        $service = new ItemInstanceService();
        $first = $service->promote($player->id, $bois->id);
        $second = $service->promote($player->id, $bois->id);

        $this->assertNotSame(
            (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$first]),
            (int) $this->link->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$second])
        );
    }
}
