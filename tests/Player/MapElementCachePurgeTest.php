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

    /**
     * Diagnostic : rejoue la sélection exacte de View::refresh_players_svg
     * et rend les lignes qu'elle voit. Une purge qui rate ne dit rien par
     * elle-même — c'est la donnée qui la décide qu'il faut lire.
     */
    private function explainMissedPurge(int $playerId, int $coordsId): string
    {
        $c = $this->link->fetchAssociative('SELECT x, y, z, plan FROM coords WHERE id = ?', [$coordsId]);
        $p = $this->link->fetchAssociative('SELECT id, coords_id, player_type FROM players WHERE id = ?', [$playerId]);

        $selected = $c === false ? [] : $this->link->fetchFirstColumn(
            'SELECT p.id FROM players AS p
             INNER JOIN coords AS c ON p.coords_id = c.id
             WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ? AND c.z = ? AND c.plan = ?',
            [$c['x'] - 20, $c['x'] + 20, $c['y'] - 20, $c['y'] + 20, $c['z'], $c['plan']]
        );

        return 'purge manquée — case ' . json_encode($c, JSON_UNESCAPED_UNICODE)
            . ' | joueur ' . json_encode($p, JSON_UNESCAPED_UNICODE)
            . ' | joueurs sélectionnés pour purge : ' . json_encode($selected);
    }

    public function testAnElementAppearingInvalidatesTheCachedBoard(): void
    {
        $player = $this->createRealPlayer('GmPurge');
        $player->get_data();
        $this->snapshotBloodAt((int) $player->data->coords_id);

        $cache = $this->primeCacheFor((int) $player->id);

        Element::put('sang', (int) $player->data->coords_id, 3600);

        if (is_file($cache)) {
            @unlink($cache);
            $this->fail($this->explainMissedPurge((int) $player->id, (int) $player->data->coords_id));
        }

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
