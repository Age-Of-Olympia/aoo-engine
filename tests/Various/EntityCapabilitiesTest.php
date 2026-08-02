<?php

namespace Tests\Various;

use App\Entity\NonPlayerCharacter;
use App\Entity\RealPlayer;
use App\Entity\TutorialPlayer;
use App\Interface\ProgressesInterface;
use App\Interface\TakesTurnsInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Prendre un tour et progresser sont des CAPACITÉS, pas une branche.
 *
 * Les deux contrats ne sont portés que par `Character` aujourd'hui ; un
 * bâtiment jouable les portera sans jamais devenir un personnage
 * (docs/design-playable-buildings.md). Ces cas vérifient ce qui compte
 * maintenant : qu'un lecteur puisse poser la question par le CONTRAT, sans
 * connaître l'arbre — c'est tout ce qui permettra de basculer les gardes une à
 * une plus tard, au lieu d'un balayage.
 */
#[Group('entities-baseline')]
class EntityCapabilitiesTest extends TestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function characterClasses(): array
    {
        return [
            'joueur'   => [RealPlayer::class],
            'tutoriel' => [TutorialPlayer::class],
            'PNJ'      => [NonPlayerCharacter::class],
        ];
    }

    /**
     * @param class-string $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('characterClasses')]
    public function testEveryCharacterHoldsBothCapabilities(string $class): void
    {
        $entity = new $class();

        $this->assertInstanceOf(TakesTurnsInterface::class, $entity);
        $this->assertInstanceOf(ProgressesInterface::class, $entity);
    }

    /** Le tour se lit et s'écrit par le contrat, sans nommer Character. */
    public function testATurnIsReadAndWrittenThroughTheContract(): void
    {
        $entity = new RealPlayer();

        $this->assertSame(0, $this->whenIsItsTurn($entity), 'une entité neuve joue tout de suite');

        $entity->setNextTurnTime(1_800_000_000)
            ->setLastActionTime(1_799_999_000)
            ->setNextTurnRescheduled(true);

        $this->assertSame(1_800_000_000, $this->whenIsItsTurn($entity));
        $this->assertSame(1_799_999_000, $entity->getLastActionTime());
        $this->assertTrue($entity->isNextTurnRescheduled());
    }

    /** La progression aussi : de l'XP, un niveau, des points à dépenser. */
    public function testProgressionIsReadAndWrittenThroughTheContract(): void
    {
        $entity = new RealPlayer();

        $this->assertSame(0, $this->howFarAlong($entity));

        $entity->addXp(150);
        $entity->setRank(3)->setBonusPoints(2);

        $this->assertSame(150, $this->howFarAlong($entity));
        $this->assertSame(3, $entity->getRank());
        $this->assertSame(2, $entity->getBonusPoints());
    }

    /**
     * Une structure ne prend pas de tour AUJOURD'HUI.
     *
     * Le cas n'interdit rien : il date l'état. Le jour où un bâtiment jouable
     * porte les contrats, c'est ici qu'on vient le dire, et non pas au détour
     * d'un comportement qui aurait changé sans témoin.
     *
     * La question est posée à la CLASSE et non à une instance : demander
     * `assertNotInstanceOf` sur un `Building` est vrai par construction, et
     * l'analyse statique le dit avant même que le cas tourne.
     */
    public function testAStructureHoldsNeitherCapabilityYet(): void
    {
        $implemented = class_implements(\App\Entity\Building::class);

        $this->assertNotContains(TakesTurnsInterface::class, $implemented);
        $this->assertNotContains(ProgressesInterface::class, $implemented);
    }

    private function whenIsItsTurn(TakesTurnsInterface $entity): int
    {
        return $entity->getNextTurnTime();
    }

    private function howFarAlong(ProgressesInterface $entity): int
    {
        return $entity->getXp();
    }
}
