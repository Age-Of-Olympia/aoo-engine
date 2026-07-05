<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\OutcomeInstruction;
use App\Listener\OutcomeInstructionMetadataListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-outcome')]
class OutcomeInstructionMetadataListenerTest extends TestCase
{
    private function loadMetadata(): ClassMetadata
    {
        $metadata = new ClassMetadata(OutcomeInstruction::class);
        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        (new OutcomeInstructionMetadataListener())->loadClassMetadata($args);

        return $metadata;
    }

    public function testRegistersConcreteSubclassesSoStiRootQueriesMatchEveryDiscriminator(): void
    {
        // Without subClasses, a SELECT on the root builds `type IN ('outcomeinstruction')`
        // and excludes every real instruction row (the empty-outcomes bug).
        $metadata = $this->loadMetadata();

        $this->assertContains(LifeLossOutcomeInstruction::class, $metadata->subClasses);
        $this->assertArrayHasKey('lifeloss', $metadata->discriminatorMap);
    }

    public function testRootIsNotListedAsItsOwnSubclass(): void
    {
        $metadata = $this->loadMetadata();

        $this->assertNotContains(OutcomeInstruction::class, $metadata->subClasses);
    }
}
