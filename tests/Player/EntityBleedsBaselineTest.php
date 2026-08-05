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
#[Group('entities-baseline')]
class EntityBleedsBaselineTest extends LegacyPlayerFixtureTestCase
{
    /** Contenu témoin : distingue « jamais purgé » de « purgé puis reconstruit ». */
    private const CACHE_MARKER = '<svg data-temoin="1"/>';

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

        /* Partir d'une case SÈCHE. Sans cela le test passe même si plus
         * personne ne saigne : il suffit qu'un test antérieur ait laissé
         * du sang sur (0,0) pour que le compte soit déjà à 1. C'est
         * exactement ce qui a masqué une panne réelle en intégration
         * continue, où la base neuve rend la différence visible. */
        $this->link->executeStatement(
            "DELETE FROM map_elements WHERE name = 'sang' AND coords_id = ?",
            [(int) $player->data->coords_id]
        );
        $this->assertSame(0, $this->bloodCountAt((int) $player->data->coords_id), 'case sèche au départ');

        $player->putBonus(['pv' => -1]);

        $this->assertSame(
            1,
            $this->bloodCountAt((int) $player->data->coords_id),
            'un personnage blessé verse du sang (races.bleeds = sang)'
        );
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
        /* Ce test exige que la race SAIGNE : sans élément posé il n'y a
         * rien à invalider, et l'assertion échouerait loin de sa cause.
         * Toutes les bases de test n'ont pas cette colonne — la base de
         * jeu l'a, celle d'intégration continue ne l'a pas encore, d'où
         * un vert local qui ne prédisait pas la CI. */
        $player = $this->createRealPlayer('GmBleed');
        $player->get_data();
        $player->get_caracs();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        /* Le garde interroge le MÊME chemin que la production
         * (Player::putBonus), pas une requête SQL équivalente : une
         * précédente version lisait races.bleeds en direct et restait donc
         * verte alors que RaceService, lui, ne rendait rien — le test
         * échouait ensuite sur l'assertion de cache, loin de sa cause. */
        $bleeds = (new \App\Service\RaceService())
            ->getRaceByName((string) ($player->data->race ?? ''))?->getBleeds() ?? '';

        if ($bleeds === '') {
            $this->markTestSkipped(
                'la race « ' . $player->data->race . ' » ne verse rien ici : rien à invalider'
            );
        }

        $this->link->executeStatement(
            "DELETE FROM map_elements WHERE name = ? AND coords_id = ?",
            [$bleeds, (int) $player->data->coords_id]
        );

        /* Même chemin RELATIF que le code de production
         * (View::refresh_players_svg) : il s'appuie sur le répertoire
         * courant, pas sur DOCUMENT_ROOT, qui est vide hors requête. */
        $cache = 'datas/private/players/' . $player->id . '.svg';
        @mkdir(dirname($cache), 0777, true);
        file_put_contents($cache, self::CACHE_MARKER);
        $this->assertFileExists($cache, 'le cache de départ est bien en place');

        $player->putBonus(['pv' => -1]);

        /* Chaînon par chaînon : sans cette assertion, une absence de sang
         * se présente comme un défaut de purge et envoie chercher au
         * mauvais endroit. */
        $this->assertSame(
            1,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM map_elements WHERE name = ? AND coords_id = ?',
                [$bleeds, (int) $player->data->coords_id]
            ),
            'la blessure a bien versé « ' . $bleeds .' » sur la case'
        );

        /* Le contenu témoin départage les deux échecs possibles : un
         * cache jamais purgé n'est pas le même défaut qu'un cache purgé
         * puis reconstruit dans la foulée par la suite de putBonus. */
        if (is_file($cache)) {
            $left = (string) file_get_contents($cache);
            $this->fail(
                $left === self::CACHE_MARKER
                    ? 'le damier en cache n\'a pas été purgé du tout'
                    : 'le damier a été purgé PUIS reconstruit (' . strlen($left) . ' octets)'
            );
        }

        $this->assertFileDoesNotExist(
            $cache,
            'le damier mis en cache est purgé : le joueur verra son sang sans se déplacer'
        );
    }

    public function testAStructureDoesNotBleed(): void
    {
        $this->requireBuildingsOrSkip();
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure('palissade', $x, $y);

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
