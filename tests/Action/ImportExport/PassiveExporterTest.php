<?php

namespace Tests\Action\ImportExport;

use App\Entity\ActionPassive;
use App\Entity\CharacterRace;
use App\Entity\Race;
use App\Service\ImportExport\PassiveExporter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class PassiveExporterTest extends TestCase
{
    public function testObjectTypeIsPassive(): void
    {
        $this->assertSame('passive', (new PassiveExporter())->objectType());
    }

    public function testExportsTheFlatScalarsAndJsonColumns(): void
    {
        $payload = (new PassiveExporter())->toArray($this->samplePassive());

        $this->assertSame([
            'name' => 'oeil_de_lynx',
            'displayName' => 'Œil de lynx',
            'text' => '+2 CT',
            'type' => 'bonus',
            'carac' => 'ct',
            'category' => 'distance',
            'prerequisites' => 'arc',
            'race' => 'Elfe',
            'level' => 3,
            'value' => 2.0,
            'traits' => ['ct', 'agi'],
            'conditions' => ['weapon' => 'bow'],
        ], $payload);
    }

    public function testExportsNullForUninitializedScalarsFromLegacyNullColumns(): void
    {
        $passive = new ActionPassive();
        $passive->setName('partial');
        // text/type/carac/traits deliberately never set (legacy NULL rows).

        $payload = (new PassiveExporter())->toArray($passive);

        $this->assertSame('partial', $payload['name']);
        $this->assertNull($payload['text']);
        $this->assertSame([], $payload['traits']);
        $this->assertSame(0.0, $payload['value']);
        $this->assertNull($payload['conditions']);
    }

    public function testRejectsNonPassiveEntities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PassiveExporter())->toArray(new CharacterRace());
    }

    private function samplePassive(): ActionPassive
    {
        $passive = new ActionPassive();
        $passive->setName('oeil_de_lynx');
        $passive->setDisplayName('Œil de lynx');
        $passive->setText('+2 CT');
        $passive->setType('bonus');
        $passive->setCarac('ct');
        $passive->setCategory('distance');
        $passive->setPrerequisites('arc');
        $passive->setRace('Elfe');
        $passive->setLevel(3);
        $passive->setValue(2.0);
        $passive->setTraits(['ct', 'agi']);
        $passive->setConditions(['weapon' => 'bow']);

        return $passive;
    }
}
