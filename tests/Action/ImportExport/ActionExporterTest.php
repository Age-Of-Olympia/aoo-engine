<?php

namespace Tests\Action\ImportExport;

use App\Action\MeleeAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\Race;
use App\Enum\OutcomeTarget;
use App\Service\Action\ActionCatalogService;
use App\Service\ImportExport\ActionExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ActionExporterTest extends TestCase
{
    public function testObjectTypeIsAction(): void
    {
        $this->assertSame('action', (new ActionExporter())->objectType());
    }

    public function testExportsScalarsAndStiTypeFromTheConcreteSubclass(): void
    {
        $action = $this->meleeAction();

        $payload = (new ActionExporter())->toArray($action);

        $this->assertSame('attaquer', $payload['name']);
        $this->assertSame('melee', $payload['type']);
        $this->assertSame('sword', $payload['icon']);
        $this->assertSame('Attaquer', $payload['displayName']);
        $this->assertSame('Frappe au corps à corps', $payload['text']);
        $this->assertSame(1, $payload['level']);
        $this->assertSame('warrior', $payload['category']);
        $this->assertNull($payload['race']);
        $this->assertNull($payload['cost']);
        $this->assertNull($payload['prerequisites']);
    }

    public function testExportsRacesByNameSortedAndDeterministic(): void
    {
        $action = $this->meleeAction();
        $action->addRace($this->race('Nain'));
        $action->addRace($this->race('Elfe'));
        $action->addRace($this->race('Homme-Sauvage'));

        $payload = (new ActionExporter())->toArray($action);

        $this->assertSame(['Elfe', 'Homme-Sauvage', 'Nain'], $payload['races']);
    }

    public function testExportsConditionsWithNaturalKeysAndNoDbId(): void
    {
        $action = $this->meleeAction();
        $action->addCondition($this->condition('RequiresTraitValue', 0, true, ['f' => 3]));
        $action->addCondition($this->condition('Plan', 1, false, null));

        $payload = (new ActionExporter())->toArray($action);

        $this->assertSame(
            [
                ['type' => 'RequiresTraitValue', 'executionOrder' => 0, 'blocking' => true, 'parameters' => ['f' => 3]],
                ['type' => 'Plan', 'executionOrder' => 1, 'blocking' => false, 'parameters' => null],
            ],
            $payload['conditions']
        );
    }

    public function testExportsOutcomesWithEmbeddedInstructionsAndPreservedOrder(): void
    {
        $action = $this->meleeAction();

        $outcome = new ActionOutcome();
        $outcome->setName('hit');
        $outcome->setOnSuccess(true);
        $outcome->setApplyTo(OutcomeTarget::Both);
        $outcome->addInstruction($this->instruction(['amount' => 5], 0));
        $outcome->addInstruction($this->instruction(['amount' => 2], 1));
        $action->addOutcome($outcome);

        $payload = (new ActionExporter())->toArray($action);

        $this->assertSame(
            [
                [
                    'name' => 'hit',
                    'onSuccess' => true,
                    'applyTo' => 'both',
                    'instructions' => [
                        ['type' => 'lifeloss', 'orderIndex' => 0, 'parameters' => ['amount' => 5]],
                        ['type' => 'lifeloss', 'orderIndex' => 1, 'parameters' => ['amount' => 2]],
                    ],
                ],
            ],
            $payload['outcomes']
        );
    }

    public function testExportsNullForUninitializedScalarsFromLegacyNullColumns(): void
    {
        // Mirrors real rows (e.g. the "distance" action) where actions.text is
        // NULL despite the NOT NULL schema, leaving the typed property unset.
        $action = new MeleeAction();
        $action->setName('distance');
        $action->setIcon('ra-arrow-cluster');
        $action->setDisplayName('Attaquer');
        $action->setLevel(1);
        // text deliberately never set — getText() would throw.

        $payload = (new ActionExporter())->toArray($action);

        $this->assertNull($payload['text']);
        $this->assertSame('distance', $payload['name']);
        $this->assertSame('Attaquer', $payload['displayName']);
    }

    public function testRejectsNonActionEntities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ActionExporter())->toArray($this->race('Nain'));
    }

    public function testExportAllMapsEveryActionFromTheCatalog(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$this->meleeAction()]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);

        $exporter = new ActionExporter(new ActionCatalogService($em));

        $all = $exporter->exportAll();

        $this->assertCount(1, $all);
        $this->assertSame('attaquer', $all[0]['name']);
        $this->assertSame('melee', $all[0]['type']);
    }

    private function meleeAction(): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName('attaquer');
        $action->setIcon('sword');
        $action->setDisplayName('Attaquer');
        $action->setText('Frappe au corps à corps');
        $action->setLevel(1);
        $action->setCategory('warrior');

        return $action;
    }

    private function race(string $name): Race
    {
        $race = new Race();
        $race->setName($name);

        return $race;
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    private function condition(string $type, int $order, bool $blocking, ?array $parameters): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType($type);
        $condition->setExecutionOrder($order);
        $condition->setBlocking($blocking);
        $condition->setParameters($parameters);

        return $condition;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function instruction(array $parameters, int $orderIndex): LifeLossOutcomeInstruction
    {
        $instruction = new LifeLossOutcomeInstruction();
        $instruction->setParameters($parameters);
        $instruction->setOrderIndex($orderIndex);

        return $instruction;
    }
}
