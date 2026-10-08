<?php

namespace Tests\Various;

use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Repairability is a property of the TYPE (its repair recipe, see
 * BuildingRepairTest); both repair actions carry that guard.
 */
class OnlyWhatIsBuiltGetsRepairedTest extends TestCase
{
    use LegacyBootstrapTrait;

    /**
     * The action carries the guard BEFORE its cost and in display context:
     * ordered after RequiresTraitValue it would bill before refusing, and
     * without display_context the button would show on a flower and fail.
     */
    public function testReparerCarriesTheConditionBeforeItsCost(): void
    {
        $conn = $this->bootstrapLegacyOrSkip();

        foreach (['reparer', 'reparer_or'] as $action) {
            $this->assertGuardBeforeCost($conn, $action);
        }
    }

    private function assertGuardBeforeCost(\Doctrine\DBAL\Connection $conn, string $action): void
    {
        $rows = $conn->fetchAllAssociative(
            "SELECT conditionType, execution_order, blocking, display_context
               FROM action_conditions
              WHERE action_id = (SELECT id FROM actions WHERE name = ?)",
            [$action]
        );
        $this->assertNotSame([], $rows, $action . ' absent du catalogue');

        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['conditionType']] = $row;
        }

        $this->assertArrayHasKey(
            'RequiresRepairableTarget',
            $byType,
            'sans elle, toute structure redevient réparable'
        );

        $guard = $byType['RequiresRepairableTarget'];
        $this->assertSame(1, (int) $guard['blocking']);
        $this->assertSame(1, (int) $guard['display_context'], 'le bouton doit disparaître, pas échouer');

        if (isset($byType['RequiresTraitValue'])) {
            $this->assertLessThan(
                (int) $byType['RequiresTraitValue']['execution_order'],
                (int) $guard['execution_order'],
                'refuser AVANT de facturer l\'action'
            );
        }
    }
}
