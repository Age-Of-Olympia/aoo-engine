<?php

namespace Tests\Various;

use App\Action\Condition\TargetTypeCondition;
use App\Entity\GameEntity;
use App\Enum\EntityCategory;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Les familles de structures sont nommées à trois endroits — le discriminant
 * qui les crée, le libellé qui les propose au workbench, l'article qui les
 * refuse — et rien n'obligeait les trois listes à se ressembler.
 *
 * Ajouter une sixième famille sans la déclarer donnerait une action qu'on ne
 * peut pas viser, ou un refus qui dit « une structure » là où l'action répare
 * les bâtiments. Le mapping du discriminant est la source : les deux autres
 * s'alignent dessus ou ce cas échoue.
 */
#[Group('entities-baseline')]
class EntityFamiliesVocabularyTest extends TestCase
{
    public function testEveryStructureDiscriminatorIsANamedFamily(): void
    {
        $this->assertSame(
            $this->structureDiscriminators(),
            $this->sorted(array_keys(EntityCategory::structureFamilies())),
            'EntityCategory::structureFamilies() liste exactement les discriminants de structure'
        );
    }

    public function testEveryFamilyCanBeNamedInARefusal(): void
    {
        $labels = (new \ReflectionClassConstant(TargetTypeCondition::class, 'REFUSAL_LABELS'))->getValue();

        $this->assertSame(
            $this->sorted(array_keys(EntityCategory::structureFamilies())),
            $this->sorted(array_keys($labels)),
            'chaque famille sait se nommer dans un refus, avec son article'
        );
    }

    /**
     * Les discriminants de `players` que EntityCategory range du côté structure.
     *
     * @return list<string>
     */
    private function structureDiscriminators(): array
    {
        $attributes = (new \ReflectionClass(GameEntity::class))->getAttributes(DiscriminatorMap::class);

        $this->assertNotEmpty($attributes, 'GameEntity porte sa carte de discriminants');

        $structures = array_filter(
            array_keys($attributes[0]->newInstance()->value),
            static fn (string $discriminator): bool
                => EntityCategory::fromPlayerType($discriminator)->isStructure()
        );

        return $this->sorted($structures);
    }

    /**
     * @param array<int|string, string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $values = array_values($values);
        sort($values);

        return $values;
    }
}
