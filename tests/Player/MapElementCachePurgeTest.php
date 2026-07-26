<?php

namespace Tests\Player;

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
 * jeu, pour un résultat identique. D'où l'exclusion — et ces tests, qui
 * pinnent les deux moitiés de la règle.
 */
#[Group('entities-golden-master')]
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
     * L'exclusion demandée : la trace de pas ne purge pas, parce que le
     * déplacement qui la produit purge déjà les deux cases concernées.
     */
    public function testAFootstepDoesNotInvalidateTheCachedBoard(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();

        $cache = $this->primeCacheFor((int) $player->id);

        Element::put('trace_pas_n', (int) $player->data->coords_id, 3600, refreshWatchers: false);

        $this->assertFileExists(
            $cache,
            'la trace de pas ne redemande pas une purge que Player::go() vient de faire'
        );

        @unlink($cache);
    }

    /**
     * Le garde-fou qui donne son sens à l'exclusion : si go.php cessait
     * de la demander, on repaierait la purge à chaque pas sans que rien
     * ne casse visiblement.
     */
    public function testTheMovementPathStillOptsOutOfThePurge(): void
    {
        $source = file_get_contents(__DIR__ . '/../../go.php');
        $this->assertIsString($source);

        $this->assertMatchesRegularExpression(
            '/Element::put\(\$footstep,[^;]*refreshWatchers:\s*false\s*\)/s',
            $source,
            'go.php doit poser la trace de pas SANS redemander la purge'
        );
    }
}
