<?php

namespace Tests\Player;

use App\Service\MapElementService;
use App\Service\TurnScheduleService;
use Classes\Element;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Politique de purge du damier en cache quand un élément apparaît sur
 * la carte.
 *
 * Le damier de chaque joueur est un SVG sur disque. Un élément qui
 * apparaît doit l'invalider, sinon le joueur continue de recevoir sa
 * vieille image (c'est ce qui retardait l'apparition du sang jusqu'au
 * déplacement suivant).
 *
 * Mais la trace de pas est posée à CHAQUE déplacement, et Player::go()
 * purge déjà l'origine et la destination du pas : lui laisser demander
 * la purge revient à payer deux fois, sur l'action la plus fréquente du
 * jeu, pour un résultat identique. D'où une couche à part — les marques,
 * qui ne purgent jamais.
 */
#[Group('entities-baseline')]
class MapElementCachePurgeTest extends LegacyPlayerFixtureTestCase
{
    /** Cache SVG du joueur, au chemin RELATIF utilisé en production. */
    private function primeCacheFor(int $playerId): string
    {
        $cache = 'datas/private/players/' . $playerId . '.svg';
        @mkdir(dirname($cache), 0777, true);
        file_put_contents($cache, '<svg/>');
        $this->assertFileExists($cache, 'le cache de départ est bien en place');

        return $cache;
    }

    public function testAnElementAppearingInvalidatesTheCachedBoard(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        /* L'identité lue doit être celle de la base. get_data() sert un
         * cache fichier indexé par id : sur une base neuve, où les ids
         * sont recyclés d'un test à l'autre, un fichier resté en place
         * fait hériter au nouveau joueur les coordonnées de l'ancien —
         * on posait alors l'élément là où personne ne se trouve, et la
         * purge ne ratait rien du tout. */
        $this->assertSame(
            (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [(int) $player->id]),
            (int) $player->data->coords_id,
            'le joueur lu est bien celui de la base, pas un cache hérité'
        );

        $cache = $this->primeCacheFor((int) $player->id);

        Element::put('sang', (int) $player->data->coords_id, 3600);

        $this->assertFileDoesNotExist(
            $cache,
            'un élément qui apparaît purge le damier de ceux qui le voient'
        );
    }

    /**
     * Un élément sans effet du même nom — une cascade, un décor — se pose
     * et se foule sans rien appliquer. Element::put refusait tout nom
     * absent du catalogue des effets, et le pas mourait sur
     * « error effect name » pour ceux que Tiled avait posés quand même.
     */
    public function testAnElementWithoutAnEffectIsDecor(): void
    {
        $player = $this->createRealPlayer('GmDecor');
        $player->get_data();
        $coordsId = (int) $player->data->coords_id;
        $this->link->executeStatement('DELETE FROM map_elements WHERE coords_id = ?', [$coordsId]);

        try {
            $this->assertTrue(Element::put('cascade_test_decor', $coordsId, Element::DURATION_INFINITE), 'posé sans effet');
            $this->assertSame(
                1,
                (int) $this->link->fetchOne('SELECT COUNT(*) FROM map_elements WHERE name = ? AND coords_id = ?', ['cascade_test_decor', $coordsId])
            );

            $player->get_caracs();
            $cell = $this->link->fetchAssociative('SELECT x, y, z, plan FROM coords WHERE id = ?', [$coordsId]);
            $player->go((object) $cell);

            $this->assertSame(
                0,
                (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_effects WHERE player_id = ? AND name = ?', [(int) $player->id, 'cascade_test_decor']),
                'fouler un décor n\'applique rien'
            );
        } finally {
            $this->link->executeStatement('DELETE FROM map_elements WHERE name = ?', ['cascade_test_decor']);
        }
    }

    /**
     * Les durées s'écrivent en TOURS des deux côtés — effets comme
     * éléments — mais un élément de carte n'appartient à aucun joueur :
     * aucun tour ne le décrémente, c'est le cron horaire qui l'efface.
     * Sa durée reste donc une échéance, calculée sur le tour de
     * référence (vitesse 16, soit 18 h).
     */
    public function testAnElementDurationIsWrittenInTurnsAndLivedInRealTime(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        $before = time();
        Element::put('sang', (int) $player->data->coords_id, 2);

        $endTime = (int) $this->link->fetchOne(
            "SELECT endTime FROM map_elements WHERE name = 'sang' AND coords_id = ?",
            [(int) $player->data->coords_id]
        );

        $expected = $before + (2 * TurnScheduleService::referenceTurnSeconds());
        $this->assertGreaterThanOrEqual($expected, $endTime);
        $this->assertLessThanOrEqual($expected + 5, $endTime, 'deux tours de référence, soit 36 h');
    }

    /** Ce que le temps n'use pas : endTime 0, que le cron laisse passer. */
    public function testAnEndlessElementIsWrittenAsNeverExpiring(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        Element::put('sang', (int) $player->data->coords_id, Element::DURATION_INFINITE);

        $this->assertSame(
            0,
            (int) $this->link->fetchOne(
                "SELECT endTime FROM map_elements WHERE name = 'sang' AND coords_id = ?",
                [(int) $player->data->coords_id]
            ),
            'la convention que scripts/crons/hourly/delete_elements.php ne purge jamais'
        );
    }

    /**
     * Une marque ne purge pas : le déplacement qui laisse la trace de pas
     * purge déjà les deux cases concernées.
     */
    public function testAFootstepDoesNotInvalidateTheCachedBoard(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();
        $coordsId = (int) $player->data->coords_id;

        $cache = $this->primeCacheFor((int) $player->id);

        (new MapElementService())->putMark('trace_pas_n', $coordsId, 1);

        $this->assertFileExists(
            $cache,
            'la trace de pas ne redemande pas une purge que Player::go() vient de faire'
        );
        $this->assertSame(1, (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM map_marks WHERE name = 'trace_pas_n' AND coords_id = ?",
            [$coordsId]
        ));

        $this->link->executeStatement("DELETE FROM map_marks WHERE name = 'trace_pas_n' AND coords_id = ?", [$coordsId]);
        @unlink($cache);
    }
}
