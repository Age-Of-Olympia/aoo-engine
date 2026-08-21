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
    /**
     * Le type que ce couple de colonnes décrit — classe comprise.
     *
     * `of()` ne déduit plus la famille des colonnes : elle la demande à la
     * classe. Le test passe donc par la seule dérivation qui reste,
     * {@see Race::ofFamily()}, et vérifie la chaîne entière — couple de
     * colonnes, classe, visage — au lieu d'une règle recopiée.
     */
    private function race(string $kind, string $nature = 'edifice'): Race
    {
        $race = Race::ofFamily($kind, $nature);
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
        $this->assertSame(TypeEditorFace::RESOURCE, TypeEditorFace::of($this->race('structure', 'ressource'))->key);
    }

    /** Each list keeps its own, so decor never swells the building types. */
    public function testEachFaceKeepsOnlyItsOwn(): void
    {
        $rows = [
            $this->race('character'),
            $this->race('structure'),
            $this->race('structure', 'obstacle'),
            $this->race('structure', 'decor'),
            $this->race('structure', 'ressource'),
        ];

        $kept = static fn(TypeEditorFace $face): int => count(array_filter(
            $rows,
            static fn(Race $race): bool => $face->keeps($race)
        ));

        $this->assertSame(1, $kept(TypeEditorFace::character()));
        $this->assertSame(2, $kept(TypeEditorFace::building()));
        $this->assertSame(1, $kept(TypeEditorFace::scenery()));
        $this->assertSame(
            1,
            $kept(TypeEditorFace::resource()),
            'un récoltable ne doit plus grossir la liste des bâtiments'
        );
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
        $this->assertSame(
            TypeEditorFace::RESOURCE,
            TypeEditorFace::fromRequest(['kind' => 'structure', 'nature' => 'ressource'])->key
        );
    }

    /** A resource form must carry its nature back, or it saves onto another list. */
    public function testTheResourceFaceCarriesItsNatureThroughAPost(): void
    {
        $fields = TypeEditorFace::resource()->formFields();

        $this->assertStringContainsString('name="kind" value="structure"', $fields);
        $this->assertStringContainsString('name="nature" value="ressource"', $fields);
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

    /**
     * La sauvegarde écrit la nature que le visage impose.
     *
     * L'ancien garde ne couvrait que le décor : un type créé depuis Types
     * récoltables naissait « ressource » puis se faisait rabattre sur
     * « edifice » par le formulaire — et disparaissait de la palette des
     * ressources des deux éditeurs. Chaque visage hors bâtiments impose sa
     * nature ; seul le visage bâtiments offre le choix édifice/obstacle.
     */
    public function testASaveKeepsTheNatureOfItsFace(): void
    {
        // Le formulaire ne poste rien, ou poste la valeur d'un autre visage :
        // la nature du visage gagne toujours.
        foreach ([null, '', 'edifice', 'obstacle'] as $posted) {
            $this->assertSame(TypeEditorFace::NATURE_RESOURCE, TypeEditorFace::resource()->resolveNature($posted));
            $this->assertSame(TypeEditorFace::NATURE_PLANT, TypeEditorFace::plant()->resolveNature($posted));
            $this->assertSame(TypeEditorFace::NATURE_DECOR, TypeEditorFace::scenery()->resolveNature($posted));
        }

        // Le visage bâtiments est le seul à offrir un choix.
        $this->assertSame('obstacle', TypeEditorFace::building()->resolveNature('obstacle'));
        $this->assertSame('edifice', TypeEditorFace::building()->resolveNature('edifice'));
        $this->assertSame('edifice', TypeEditorFace::building()->resolveNature(null));

        // Les personnages gardent le défaut historique de la colonne.
        $this->assertSame('edifice', TypeEditorFace::character()->resolveNature(null));
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
