<?php

namespace Tests\Player;

use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Saignement par race (races.bleeds) : ce qu'une entité verse au sol
 * quand elle est blessée est une carac de RACE — 'sang' pour les
 * personnages (comportement historique), rien pour les structures
 * (un mur ne saigne pas). Le déclencheur reste dans putBonus.
 */
#[Group('entities-golden-master')]
class EntityBleedsGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    private function bloodCountAt(int $coordsId): int
    {
        return (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM map_elements WHERE name = 'sang' AND coords_id = ?",
            [$coordsId]
        );
    }

    public function testACharacterStillBleedsBlood(): void
    {
        $player = $this->createRealPlayer('GmBleed');
        $player->get_data();
        $player->get_caracs();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        $before = $this->bloodCountAt((int) $player->data->coords_id);
        $player->putBonus(['pv' => -1]);

        $this->assertGreaterThanOrEqual(
            1,
            $this->bloodCountAt((int) $player->data->coords_id),
            'un personnage blessé verse du sang (races.bleeds = sang)'
        );
        // $before peut déjà être 1 (élément unique par case selon Element::put)
        $this->assertGreaterThanOrEqual($before, $this->bloodCountAt((int) $player->data->coords_id));
    }

    /**
     * Le damier de chaque joueur est un SVG mis en cache sur disque. Le
     * sang était bien écrit en base, mais rien n'invalidait ce cache :
     * le joueur continuait de recevoir sa vieille image et ne voyait
     * donc sa blessure qu'après s'être déplacé. Le rafraîchissement
     * côté client existait déjà — il rapatriait le même SVG périmé.
     *
     * C'est l'invalidation qui manquait, et c'est elle qu'on épingle :
     * sans elle, le correctif JavaScript reste inerte.
     */
    public function testBleedingInvalidatesTheCachedBoardOfWhoeverSeesIt(): void
    {
        $player = $this->createRealPlayer('GmBleed');
        $player->get_data();
        $player->get_caracs();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        /* Même chemin RELATIF que le code de production
         * (View::refresh_players_svg) : il s'appuie sur le répertoire
         * courant, pas sur DOCUMENT_ROOT, qui est vide hors requête. */
        $cache = 'datas/private/players/' . $player->id . '.svg';
        @mkdir(dirname($cache), 0777, true);
        file_put_contents($cache, '<svg/>');
        $this->assertFileExists($cache, 'le cache de départ est bien en place');

        $player->putBonus(['pv' => -1]);

        $this->assertFileDoesNotExist(
            $cache,
            'le damier mis en cache est purgé : le joueur verra son sang sans se déplacer'
        );
    }

    public function testAStructureDoesNotBleed(): void
    {
        $this->requireBuildingsOrSkip();
        $id = $this->placeStructure('palissade', 0, 3);

        $building = \App\Factory\PlayerFactory::legacy($id);
        $building->get_data();
        $building->get_caracs();
        $this->snapshotBloodAt((int) $building->data->coords_id);

        $before = $this->bloodCountAt((int) $building->data->coords_id);
        $building->putBonus(['pv' => -5]);

        $this->assertSame(
            $before,
            $this->bloodCountAt((int) $building->data->coords_id),
            'une structure blessée ne verse RIEN (races.bleeds vide pour la sorte structure)'
        );
    }
}
