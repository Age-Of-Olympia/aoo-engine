<?php

namespace Tests\Various;

use App\Entity\Race;
use App\View\Admin\TypeEditorFace;
use PHPUnit\Framework\TestCase;

/**
 * Which face of the type editor a row belongs to.
 *
 * One table, three populations — playable races, building types, scenery
 * types — sharing one editor. A boolean could only name two of them.
 */
class TypeEditorFaceTest extends TestCase
{
    private function race(string $kind, string $nature = 'edifice'): Race
    {
        $race = new Race();
        $race->setKind($kind);
        $race->setStructureNature($nature);

        return $race;
    }

    public function testARowLandsOnItsOwnFace(): void
    {
        $this->assertSame(TypeEditorFace::CHARACTER, TypeEditorFace::of($this->race('character'))->key);
        $this->assertSame(TypeEditorFace::BUILDING, TypeEditorFace::of($this->race('structure'))->key);
        $this->assertSame(TypeEditorFace::BUILDING, TypeEditorFace::of($this->race('structure', 'obstacle'))->key);
        $this->assertSame(TypeEditorFace::SCENERY, TypeEditorFace::of($this->race('structure', 'decor'))->key);
    }

    /** Each list keeps its own, so decor never swells the building types. */
    public function testEachFaceKeepsOnlyItsOwn(): void
    {
        $rows = [
            $this->race('character'),
            $this->race('structure'),
            $this->race('structure', 'obstacle'),
            $this->race('structure', 'decor'),
        ];

        $kept = static fn(TypeEditorFace $face): int => count(array_filter(
            $rows,
            static fn(Race $race): bool => $face->keeps($race)
        ));

        $this->assertSame(1, $kept(TypeEditorFace::character()));
        $this->assertSame(2, $kept(TypeEditorFace::building()));
        $this->assertSame(1, $kept(TypeEditorFace::scenery()));
    }

    public function testTheRequestChoosesTheFace(): void
    {
        $this->assertSame(TypeEditorFace::CHARACTER, TypeEditorFace::fromRequest([])->key);
        $this->assertSame(
            TypeEditorFace::BUILDING,
            TypeEditorFace::fromRequest(['kind' => 'structure'])->key
        );
        $this->assertSame(
            TypeEditorFace::SCENERY,
            TypeEditorFace::fromRequest(['kind' => 'structure', 'nature' => 'decor'])->key
        );
    }

    /** A decor form must carry its nature back, or it saves onto another list. */
    public function testTheSceneryFaceCarriesItsNatureThroughAPost(): void
    {
        $fields = TypeEditorFace::scenery()->formFields();

        $this->assertStringContainsString('name="kind" value="structure"', $fields);
        $this->assertStringContainsString('name="nature" value="decor"', $fields);

        $this->assertStringNotContainsString('nature', TypeEditorFace::building()->formFields());
        $this->assertSame('', TypeEditorFace::character()->formFields());
    }

    /** Scenery is a structure: its images come from the structure stock. */
    public function testSceneryCountsAsAStructure(): void
    {
        $this->assertTrue(TypeEditorFace::scenery()->isStructure());
        $this->assertTrue(TypeEditorFace::building()->isStructure());
        $this->assertFalse(TypeEditorFace::character()->isStructure());

        $this->assertTrue(TypeEditorFace::scenery()->isScenery());
        $this->assertFalse(TypeEditorFace::building()->isScenery());
    }
}
