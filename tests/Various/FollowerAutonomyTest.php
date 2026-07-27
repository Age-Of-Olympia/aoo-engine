<?php

namespace Tests\Various;

use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Un suivant n'est pas du décor.
 *
 * L'étal du marchand et le double d'illusion accompagnent un personnage.
 * Ils vivaient dans `map_foregrounds`, au milieu des rochers, et
 * `players_followers` ne portait qu'un pointeur — d'où deux défauts que ces
 * cas verrouillent.
 *
 * Le premier est franc : `add_follower()` posait un décor au nom CODÉ EN
 * DUR (`marchand`), quel que soit le suivant demandé, puis le relisait par
 * le nom réclamé. Poser un double déréférençait null.
 *
 * Le second est sournois : la relecture pouvait adopter un décor DÉJÀ posé
 * sur la case, que la dépose supprimait ensuite. Sur la carte de
 * production, 19 décors `marchand` n'appartiennent à personne et étaient à
 * portée de ce geste.
 */
#[Group('items-golden-master')]
class FollowerAutonomyTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_suivants';

    protected function tearDown(): void
    {
        $link = $this->link;

        $link->executeStatement(
            'DELETE f FROM players_followers f JOIN coords c ON c.id = f.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );
        $link->executeStatement(
            'DELETE m FROM map_foregrounds m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    /** @return list<array{name: string, coords_id: int, params: string}> */
    private function followersOf(int $playerId): array
    {
        /** @var list<array{name: string, coords_id: int, params: string}> */
        return $this->link->fetchAllAssociative(
            'SELECT name, coords_id, params FROM players_followers WHERE player_id = ? ORDER BY name',
            [$playerId]
        );
    }

    private function placeOn(\Classes\Player $player, int $x, int $y): int
    {
        $id = $this->coordsId($x, $y);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);
        $player->get_data();

        return $id;
    }

    /** Un suivant se pose sous le nom demandé, sur la case de son porteur. */
    public function testAFollowerCarriesTheNameItWasAskedFor(): void
    {
        $player = $this->createRealPlayer('GmSuivant');
        $tile = $this->placeOn($player, 0, 0);

        $player->add_follower('doubles/' . $player->id, 'on');

        $followers = $this->followersOf((int) $player->id);
        $this->assertCount(1, $followers);
        $this->assertSame('doubles/' . $player->id, $followers[0]['name'], 'le nom demandé, pas « marchand »');
        $this->assertSame($tile, (int) $followers[0]['coords_id']);
    }

    /**
     * LE défaut fermé : poser puis retirer un suivant ne touche pas au décor.
     *
     * On sème d'abord un décor `marchand` sur la case — celui d'un animateur
     * —, exactement la situation où l'ancienne version l'adoptait puis
     * l'effaçait.
     */
    public function testPlacingAndRemovingAFollowerLeavesTheDecorAlone(): void
    {
        $player = $this->createRealPlayer('GmMarchand');
        $tile = $this->placeOn($player, 1, 0);

        $this->link->executeStatement(
            "INSERT INTO map_foregrounds (name, coords_id) VALUES ('marchand', ?)",
            [$tile]
        );

        $player->add_follower('marchand', 'on');
        $player->delete_follower('marchand');

        $this->assertSame([], $this->followersOf((int) $player->id), 'le suivant est parti');
        $this->assertSame(
            1,
            (int) $this->link->fetchOne(
                "SELECT COUNT(*) FROM map_foregrounds WHERE name = 'marchand' AND coords_id = ?",
                [$tile]
            ),
            'le décor de l\'animateur est resté'
        );
    }

    /**
     * `on` suit le porteur, `last` reste sur la case quittée.
     *
     * C'est ce qui distingue un double, qui marche avec vous, d'un étal, qui
     * demeure là où on l'a dressé.
     */
    public function testOnFollowsAndLastStaysBehind(): void
    {
        $player = $this->createRealPlayer('GmDeuxSuivants');
        $depart = $this->placeOn($player, 2, 0);

        $player->add_follower('doubles/' . $player->id, 'on');
        $player->add_follower('marchand', 'last');

        $arrivee = $this->coordsId(3, 0);
        $player->move_followers($arrivee);

        $positions = [];
        foreach ($this->followersOf((int) $player->id) as $row) {
            $positions[$row['name']] = (int) $row['coords_id'];
        }

        $this->assertSame($arrivee, $positions['doubles/' . $player->id], '« on » avance');
        $this->assertSame($depart, $positions['marchand'], '« last » reste en arrière');
    }

    /** Retirer un suivant ne retire que le sien, pas celui du voisin. */
    public function testRemovingAFollowerSparesTheOtherPlayers(): void
    {
        $mine = $this->createRealPlayer('GmMien');
        $other = $this->createRealPlayer('GmVoisin');
        $tile = $this->placeOn($mine, 4, 0);
        $this->placeOn($other, 4, 0);

        $mine->add_follower('marchand', 'on');
        $other->add_follower('marchand', 'on');

        $mine->delete_follower('marchand');

        $this->assertSame([], $this->followersOf((int) $mine->id));
        $this->assertCount(1, $this->followersOf((int) $other->id), 'le voisin garde le sien');
    }
}
