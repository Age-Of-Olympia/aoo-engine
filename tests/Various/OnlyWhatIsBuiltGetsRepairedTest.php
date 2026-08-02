<?php

namespace Tests\Various;

use App\Entity\BuildingType;
use App\Entity\PlantType;
use App\Entity\ResourceType;
use App\Entity\SceneryType;
use App\Entity\StructureType;
use PHPUnit\Framework\TestCase;

/**
 * Repairability is a property of the TYPE, not of the category — which has two
 * values and cannot tell a forge from a flower. These cases pin where the
 * answer lives.
 */
class OnlyWhatIsBuiltGetsRepairedTest extends TestCase
{
    /** Cases touching a real row, or a legacy object that connects on birth. */
    private function bootstrapOrSkip(): \Doctrine\DBAL\Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            $conn = \App\Factory\EntityManagerFactory::getEntityManager()->getConnection();
            $conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        return $conn;
    }

    /** What someone erected is maintained; what grows follows exhaustion and regrowth. */
    public function testWhatWasErectedIsRepairableByDefault(): void
    {
        $this->assertTrue((new BuildingType())->isRepairable(), 'un édifice s\'entretient');
        $this->assertTrue((new SceneryType())->isRepairable(), 'une statue, une clôture aussi');

        $this->assertFalse((new PlantType())->isRepairable(), 'on ne répare pas une fleur');
        $this->assertFalse((new ResourceType())->isRepairable(), 'ni un rocher');
    }

    /**
     * A type may contradict its family both ways — the reason the column
     * exists: one mendable fence must not force a whole family to move.
     */
    public function testATypeCanOverrideItsFamilyBothWays(): void
    {
        $resource = (new ResourceType())->setRepairable(true);
        $this->assertTrue($resource->isRepairable(), 'un puits de pierre taillée, pourquoi pas');

        $building = (new BuildingType())->setRepairable(false);
        $this->assertFalse($building->isRepairable(), 'une ruine se visite, elle ne se relève pas');
    }

    /**
     * Undecided means the FAMILY answers, and the override reads back as null —
     * the settings screen needs that third state.
     */
    public function testUndecidedStaysUndecidedAndFollowsTheFamily(): void
    {
        $plant = new PlantType();

        $this->assertNull($plant->getRepairableOverride(), 'rien n\'a été décidé sur ce type');
        $this->assertFalse($plant->repairableFamilyDefault());

        $plant->setRepairable(true);
        $this->assertTrue($plant->getRepairableOverride());

        $plant->setRepairable(null);
        $this->assertNull($plant->getRepairableOverride(), 'on peut rendre la décision à la famille');
        $this->assertFalse($plant->isRepairable());
    }

    /**
     * A placed object stays repairable: its type lives in `items`, which
     * getRaceByName() never finds, so a guard reading `races` alone refuses it.
     */
    public function testADroppedObjectStaysRepairable(): void
    {
        $condition = new \App\Action\Condition\RequiresRepairableTargetCondition();

        $this->bootstrapOrSkip();

        $verdict = function (string $playerType, string $race) use ($condition): bool {
            $actor = new \Classes\Player(1);
            $target = new \Classes\Player(2);
            /* On fabrique la seule chose que la garde regarde, sans base :
             * ce que porte la ligne `players`. */
            $target->data = (object) ['player_type' => $playerType, 'race' => $race];

            return $condition->check(
                $actor,
                $target,
                new \App\Entity\ActionCondition(),
                new \App\Action\Condition\ConditionObject()
            )->isSuccess();
        };

        $this->assertTrue(
            $verdict(\App\Service\ItemInstanceService::ENTITY_TYPE, 'coffre_bois'),
            'un coffre, une arme lâchée : posés, ils se réparent'
        );

        /* The same type NAME without being an exemplar is refused: the
         * discriminator opens the door, not a magic string. */
        $this->assertFalse($verdict('scenery', 'coffre_bois'));
    }

    /** The capability belongs to PLACED things, not to peoples. */
    public function testRepairabilityBelongsToPlacedThings(): void
    {
        $this->assertInstanceOf(StructureType::class, new BuildingType());
        $this->assertFalse(
            method_exists(\App\Entity\CharacterRace::class, 'isRepairable'),
            'un personnage se soigne, il ne se répare pas'
        );
    }

    /**
     * The action carries the guard BEFORE its cost and in display context:
     * ordered after RequiresTraitValue it would bill before refusing, and
     * without display_context the button would show on a flower and fail.
     */
    public function testReparerCarriesTheConditionBeforeItsCost(): void
    {
        $conn = $this->bootstrapOrSkip();

        $rows = $conn->fetchAllAssociative(
            "SELECT conditionType, execution_order, blocking, display_context
               FROM action_conditions
              WHERE action_id = (SELECT id FROM actions WHERE name = 'reparer')"
        );

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
