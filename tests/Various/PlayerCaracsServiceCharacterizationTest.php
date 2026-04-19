<?php

namespace Tests\Various;

use App\Entity\PlayerEntity;
use App\Factory\PlayerFactory;
use App\Service\PlayerCaracsService;
use Classes\Player;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test for Phase 3.4b's PlayerCaracsService extraction.
 *
 * Pins the invariant that the service's `computeNudeCaracs()`
 * produces the exact same stdClass as legacy
 * `Classes\Player::get_caracs(nude: true)` followed by reading
 * `$player->caracs`. If the extraction silently diverges — race
 * lookup wrong, upgrade count wrong, missing CARACS key — this test
 * fails.
 *
 * BourrinsView is the only current caller of the nude path; if this
 * contract holds, BourrinsView can migrate to the entity path in a
 * follow-up MR without behaviour change.
 *
 * Skips cleanly when the DB is unreachable. Uses aoo4 (not aoo4_test)
 * because legacy `Player::get_caracs` traverses
 * `datas/public/races/*.json` files, which the devcontainer aoo4 has
 * populated but aoo4_test doesn't guarantee.
 */
class PlayerCaracsServiceCharacterizationTest extends TestCase
{
    private int $playerId = 0;

    protected function setUp(): void
    {
        $this->playerId = $this->bootstrapOrSkip();
    }

    #[Group('player-caracs-characterization')]
    #[Group('phase-3-4b')]
    public function testServiceOutputMatchesLegacyNudeCaracs(): void
    {
        // Legacy side: full Player construction, then get_caracs(nude: true),
        // then snapshot `->caracs`.
        $legacy = PlayerFactory::legacy($this->playerId);
        $legacy->get_caracs(nude: true);
        $legacyCaracs = clone $legacy->caracs;

        // Modern side: service computes from (playerId, race) alone.
        $service = new PlayerCaracsService();
        $modernCaracs = $service->computeNudeCaracs(
            $this->playerId,
            (string) $legacy->data->race
        );

        // Every CARACS key must match to the int.
        foreach (CARACS as $k => $_) {
            $this->assertSame(
                $legacyCaracs->$k ?? null,
                $modernCaracs->$k ?? null,
                "nude carac mismatch on '{$k}'"
            );
        }
    }

    #[Group('player-caracs-characterization')]
    #[Group('phase-3-4b')]
    public function testServiceHandlesUnknownRaceWithZeroedRaceContribution(): void
    {
        // Use a synthetic player id with no rows in players_upgrades —
        // picking PHP_INT_MAX guarantees no collision with real rows.
        // The unknown race then contributes 0, and without any upgrade
        // rows the total is 0 for every CARACS key.
        $service = new PlayerCaracsService();

        $caracs = $service->computeNudeCaracs(
            PHP_INT_MAX,
            'phase34NoSuchRace_' . bin2hex(random_bytes(4))
        );

        foreach (CARACS as $k => $_) {
            $this->assertObjectHasProperty(
                $k,
                $caracs,
                "missing CARACS key '{$k}' when race is unknown"
            );
            $this->assertSame(
                0,
                $caracs->$k,
                "unknown race + no upgrades must default '{$k}' to 0"
            );
        }
    }

    #[Group('player-caracs-characterization')]
    #[Group('phase-3-4b')]
    public function testEntityDelegatesToServiceWithOwnIdAndRace(): void
    {
        // Bridge pin: PlayerEntity::getNudeCaracs delegates 1:1 to the
        // service. Uses a synthetic non-hydrated entity so the test
        // doesn't need the aoo4 DB to carry every entity-column (the
        // hydration test in Phase 3.1 is where schema drift gets
        // caught; this test is about the delegation shape only).
        $entity = new \App\Entity\RealPlayer();
        $this->setProtectedProperty($entity, 'id', $this->playerId);
        $this->setProtectedProperty($entity, 'race', 'nain');

        $service = new PlayerCaracsService();
        $expected = $service->computeNudeCaracs($this->playerId, 'nain');
        $actual = $entity->getNudeCaracs($service);

        foreach (CARACS as $k => $_) {
            $this->assertSame(
                $expected->$k ?? null,
                $actual->$k ?? null,
                "entity domain method diverged from service on '{$k}'"
            );
        }
    }

    /**
     * Sets a protected property via reflection — used to seed a
     * PlayerEntity without running the full Doctrine hydration path.
     */
    private function setProtectedProperty(object $instance, string $name, mixed $value): void
    {
        $ref = new \ReflectionProperty($instance, $name);
        $ref->setValue($instance, $value);
    }

    /**
     * Bootstrap legacy and locate a real player. Skips cleanly on any
     * failure.
     */
    private function bootstrapOrSkip(): int
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy DB unreachable: ' . $e->getMessage());
        }

        try {
            $row = $link->fetchAssociative(
                "SELECT id FROM players WHERE id > 0 AND (player_type IS NULL OR player_type = 'real') ORDER BY id ASC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $this->markTestSkipped('players table unreadable: ' . $e->getMessage());
        }

        if (empty($row['id'])) {
            $this->markTestSkipped(
                'No real player row available — run scripts/testing/reset_test_database.sh.'
            );
        }

        return (int) $row['id'];
    }
}
