<?php

namespace Tests\Player;

use App\Enum\EquipResult;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La bascule équiper/déséquiper se décide PAR LIGNE cliquée, plus par
 * objet catalogue (retour terrain saison 3) : un arc abîmé porté
 * transformait « équiper » le lot d'arcs neufs en déséquipement.
 *
 *   - clickedEquippedLine=false (clic sur une ligne NON portée) :
 *     on équipe — un autre exemplaire porté du même objet est
 *     REMPLACÉ, pas déséquipé-et-rien-d'autre ;
 *   - sans contexte de ligne (null) : la bascule héritée par objet
 *     catalogue demeure — mort, désarmement, revert Ae et munitions
 *     auto en dépendent.
 */
#[Group('items-golden-master')]
class EquipLineToggleGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    public function testEquipGestureOnAFreshLineReplacesTheWornEquippedInstance(): void
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();

        $player = $this->createRealPlayer('GmToggle');
        $item->add_item($player, 3);
        $player->get_caracs();

        $player->equip($item);
        $worn = (int) $this->link->fetchOne(
            "SELECT instance_id FROM players_items_instances WHERE player_id = ? AND equiped != ''",
            [$player->id]
        );
        $this->assertGreaterThan(0, $worn, 'un exemplaire est porté après le premier équipement');
        $this->link->executeStatement('UPDATE item_instances SET durability = durability - 20 WHERE id = ?', [$worn]);

        // Geste « équiper » sur la ligne de PILE (non portée, pas d'instance).
        $result = $player->equip($item, instanceId: null, clickedEquippedLine: false);

        $this->assertSame(EquipResult::Equip, $result, 'le geste équipe au lieu de basculer en déséquipement');
        $this->assertSame('', (string) $this->link->fetchOne(
            'SELECT equiped FROM players_items_instances WHERE instance_id = ?', [$worn]
        ), "l'exemplaire abîmé est remplacé : déséquipé, pas détruit");

        $equipped = $this->link->fetchAssociative(
            "SELECT l.instance_id, i.durability, i.durability_max
             FROM players_items_instances l JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND l.equiped != ''",
            [$player->id]
        );
        $this->assertNotFalse($equipped, 'un exemplaire est porté après le remplacement');
        $this->assertNotSame($worn, (int) $equipped['instance_id'], "c'est un exemplaire NEUF qui est porté");
        $this->assertSame(
            (int) $equipped['durability_max'],
            (int) $equipped['durability'],
            'l\'exemplaire porté vient de la pile : durabilité pleine'
        );

        // Sans contexte de ligne, la bascule héritée déséquipe toujours
        // (désarmement, mort, revert Ae).
        $this->assertSame(EquipResult::Unequip, $player->equip($item));
        $this->assertFalse($this->link->fetchOne(
            "SELECT 1 FROM players_items_instances WHERE player_id = ? AND equiped != ''",
            [$player->id]
        ), 'plus rien de porté après la bascule héritée');
    }
}
