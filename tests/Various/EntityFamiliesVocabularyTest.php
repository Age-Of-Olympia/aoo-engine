<?php

namespace Tests\Various;

use App\Action\Condition\TargetTypeCondition;
use App\Entity\GameEntity;
use App\Enum\EntityCategory;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Structure families are named in three places — the discriminator that creates
 * them, the label offering them in the workbench, the article refusing them.
 * The discriminator map is the source; the other two must match it or a new
 * family ships unaimable, or refused with the wrong words.
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
     * The `players` discriminators EntityCategory files under structure.
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
