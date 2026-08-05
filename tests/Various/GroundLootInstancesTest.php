<?php

namespace Tests\Various;

use App\Service\GroundLootService;
use App\Service\Map\EntityLocationService;
use Classes\View;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La liste au sol expose `instance_id` pour un exemplaire tombé.
 *
 * GroundLootView lit `$row->instance_id` pour le bouton « Ramasser cette
 * ligne » (data-instance → pickup.php → collectInstance, qui attend l'id
 * d'item_instances) ; listAt renvoyait `i.id` sans alias — Warning
 * « Undefined property: stdClass::$instance_id » sur toute case portant un
 * exemplaire au sol, et un bouton de ligne à l'id 0.
 */
class GroundLootInstancesTest extends LegacyPlayerFixtureTestCase
{
    public function testADroppedExemplarExposesItsInstanceId(): void
    {
        [$x, $y] = $this->farTile();
        $entityId = $this->installExemplar('coffre_bois', $x, $y);

        /* Au sol, pas installé : la bourse ne liste que le slot dropped. */
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => 'gaia']);
        (new EntityLocationService($this->link))->dropOnCell($entityId, $coordsId);

        $instanceId = (int) $this->link->fetchOne(
            'SELECT id FROM item_instances WHERE entity_id = ?',
            [$entityId]
        );

        $instances = (new GroundLootService())->listAt($x, $y, 0, 'gaia')['instances'];

        $this->assertCount(1, $instances);
        $this->assertSame(
            $instanceId,
            (int) ($instances[0]->instance_id ?? 0),
            'le bouton de ligne porte l\'id d\'item_instances, celui que pickup.php ramasse'
        );
    }
}
