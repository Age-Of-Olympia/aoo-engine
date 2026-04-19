<?php

namespace Tests\Tutorial;

use App\Tutorial\Steps\Movement\MovementStep;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionProperty;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * D4 Phase C — integration coverage for MovementStep's DB-touching
 * validation branches.
 *
 * The sibling MovementStepTest (Phase B) covers the two pure branches
 * (`any_movement`, `specific_count`) with reflection-primed config and
 * no DB. This class covers the three branches that read live player
 * state via `TutorialHelper::loadActivePlayer()`:
 *
 *   - `movements_depleted`   — reads $player->getRemaining('mvt')
 *   - `position`             — reads $player->getCoords()
 *   - `adjacent_to_position` — reads $player->getCoords()
 *
 * These are read-only probes. Rather than mutating turn JSON or moving
 * the player (which would require legacy filesystem + DB side effects
 * the transaction rollback can't undo), we seed a throwaway tutorial
 * player at a known coord inside the transaction and point $_SESSION
 * at them. MovementStep then reads the player's real state and the
 * test asserts the expected outcome for each config.
 */
class MovementStepDbBranchesTest extends TutorialIntegrationTestCase
{
    private int $playerId = 0;
    private int $coordX = 0;
    private int $coordY = 0;

    /** @var string|null previous ini value for error_log */
    private ?string $previousErrorLog = null;

    protected function setUp(): void
    {
        parent::setUp();

        // MovementStep and Classes\Player emit debug lines via error_log.
        // PHPUnit's beStrictAboutOutputDuringTests flags any runtime
        // output as risky. Disable error-log entirely for the duration
        // of the test; behaviour of the code under test is unaffected.
        // MovementStep emits runtime diagnostics via error_log which,
        // in CLI with log_errors=1 and the default error_log target,
        // lands on STDERR — and PHPUnit's beStrictAboutOutputDuringTests
        // flags any test-time output as risky. Route it to a throwaway
        // file so the output capture stays clean.
        $this->previousErrorLog = ini_get('error_log') ?: '';
        ini_set('error_log', '/tmp/phpunit-movement-step.log');
        ob_start();

        // Legacy helpers (functions.php defines `db()` which returns
        // $GLOBALS['link']; constants.php populates CARACS etc. used
        // inside Player::get_caracs). Load them here instead of
        // config/bootstrap.php: bootstrap would overwrite $link to
        // the production aoo4 connection, and our seeded rows inside
        // the test transaction on $this->conn would then be invisible
        // to Classes\Db. Pointing $GLOBALS['link'] at $this->conn keeps
        // every Player read inside the same transaction we rollback.
        require_once __DIR__ . '/../../config/db_constants.php';
        require_once __DIR__ . '/../../config/functions.php';
        // Point db() at the test harness connection BEFORE loading
        // constants.php — its getTutorialRewards() fallback instantiates
        // Classes\Db on first load, which exits if $link is unset.
        $GLOBALS['link'] = $this->conn;
        require_once __DIR__ . '/../../config/constants.php';

        // Seed a dedicated test player at coords (5, 7) so assertions
        // don't depend on whatever state existing fixtures sit in.
        $coordsId = $this->seedCoords(5, 7);
        $this->playerId = $this->seedPlayer($coordsId);
        $this->coordX = 5;
        $this->coordY = 7;

        // Legacy loadActivePlayer falls back to $_SESSION['playerId']
        // when no tutorial session is active.
        $_SESSION = ['playerId' => $this->playerId];
    }

    protected function tearDown(): void
    {
        // Close only our buffer — PHPUnit runs each test inside its
        // own buffer for the beStrictAboutOutputDuringTests check, and
        // closing past that leaks into the runner.
        ob_end_clean();
        $_SESSION = [];
        ini_set('error_log', $this->previousErrorLog ?? '');
        parent::tearDown();
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testPositionBranchAcceptsMatchingCoords(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'position',
            'validation_params' => ['x' => $this->coordX, 'y' => $this->coordY],
        ]);

        $this->assertTrue($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testPositionBranchRejectsNonMatchingCoords(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'position',
            'validation_params' => [
                'x' => $this->coordX + 42,
                'y' => $this->coordY + 42,
            ],
        ]);

        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testPositionBranchRejectsWhenValidationParamsMissing(): void
    {
        $step = $this->makeStepWithConfig(['validation_type' => 'position']);

        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testAdjacentToPositionAcceptsOrthogonalNeighbour(): void
    {
        // Chebyshev distance = 1: the tile directly east should match.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'adjacent_to_position',
            'validation_params' => [
                'target_x' => $this->coordX - 1,
                'target_y' => $this->coordY,
            ],
        ]);

        $this->assertTrue($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testAdjacentToPositionAcceptsDiagonalNeighbour(): void
    {
        // Contract says "including diagonals" — the north-east tile counts.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'adjacent_to_position',
            'validation_params' => [
                'target_x' => $this->coordX - 1,
                'target_y' => $this->coordY - 1,
            ],
        ]);

        $this->assertTrue($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testAdjacentToPositionRejectsSelfPosition(): void
    {
        // (deltaX + deltaY > 0) — being AT the target is not adjacent
        // to the target. Guarded in the Chebyshev check on
        // MovementStep.php:100.
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'adjacent_to_position',
            'validation_params' => [
                'target_x' => $this->coordX,
                'target_y' => $this->coordY,
            ],
        ]);

        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testAdjacentToPositionRejectsDistantTarget(): void
    {
        $step = $this->makeStepWithConfig([
            'validation_type'   => 'adjacent_to_position',
            'validation_params' => [
                'target_x' => $this->coordX + 10,
                'target_y' => $this->coordY + 10,
            ],
        ]);

        $this->assertFalse($step->validate([]));
    }

    #[Group('movement-step-db')]
    #[Group('d4-phase-c')]
    public function testMovementsDepletedBranchRejectsPlayerWithMvtBudgetRemaining(): void
    {
        // Without a turn.json file, Player::getRemaining('mvt') falls
        // back to $this->caracs->mvt (the race max — 4 for nain). That
        // is NOT zero, so `movements_depleted` must return false.
        //
        // The inverse assertion (returns true when mvt == 0) would
        // require writing a turn.json file, which the transactional
        // rollback can't undo. Exercised end-to-end by Cypress instead.
        $step = $this->makeStepWithConfig(['validation_type' => 'movements_depleted']);

        $this->assertFalse($step->validate([]));
    }

    private function seedCoords(int $x, int $y): int
    {
        $this->conn->insert('coords', [
            'x'    => $x,
            'y'    => $y,
            'z'    => 0,
            'plan' => 'tutorial',
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedPlayer(int $coordsId): int
    {
        $this->conn->insert('players', [
            'name'        => 'PhaseCMove_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'tutorial',
            'coords_id'   => $coordsId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function makeStepWithConfig(array $config): MovementStep
    {
        /** @var MovementStep $step */
        $step = (new ReflectionClass(MovementStep::class))
            ->newInstanceWithoutConstructor();

        $configProp = new ReflectionProperty($step, 'config');
        $configProp->setValue($step, $config);

        return $step;
    }
}
