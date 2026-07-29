<?php

namespace Tests\Various;

use App\Service\ResourceService;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\ScriptedDice;

/**
 * Épuisement et repousse — le comportement ACTUEL, gelé tel quel.
 *
 * Ce test ne dit pas ce qui est souhaitable, il dit ce qui se passe
 * aujourd'hui, pour qu'une migration du modèle de ressources ne puisse pas
 * déplacer l'équilibre sans qu'on le voie. Deux des comportements épinglés
 * ici sont assumés, un troisième ne l'est pas et attend sa correction —
 * chacun est signalé comme tel.
 *
 * Le corriger DANS le lot de migration rendrait toute régression
 * d'équilibrage indiscernable d'un bug de migration : on gèle d'abord, on
 * corrige ensuite, dans son propre commit.
 */
class ResourceExhaustCharacterizationTest extends TestCase
{
    /** @param array<string, mixed> $biome */
    private function plan(array $biome): object
    {
        return (object) ['biomes' => [(object) $biome]];
    }

    private function row(): object
    {
        return (object) ['id' => 42, 'name' => 'arbre1'];
    }

    protected function tearDown(): void
    {
        ResourceService::setDiceForTests(null);
    }

    /** Several nearby resources, each named so the biome can tell them apart. */
    private function rows(string ...$names): array
    {
        $rows = [];
        $id = 100;

        foreach ($names as $name) {
            $rows[] = (object) ['id' => $id++, 'name' => $name];
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $biomes */
    private function planWith(array $biomes): object
    {
        return (object) ['biomes' => array_map(static fn(array $b): object => (object) $b, $biomes)];
    }

    /**
     * GELÉ, ET FAUX — le budget d'épuisement compte les TENTATIVES, pas les
     * épuisements. Une ressource dont le biome n'a pas de taux ne peut jamais
     * s'épuiser, et pourtant elle consomme le budget : ici deux pierres sans
     * taux passent devant l'arbre, le budget de 2 est mangé, et l'arbre —
     * seul épuisable, à 100 % — survit.
     */
    public function testTheBudgetCountsAttemptsAndNotExhaustions(): void
    {
        ResourceService::setDiceForTests(new ScriptedDice([[1], [1]]));

        $plan = $this->planWith([
            ['wall' => 'pierre1', 'ressource' => 'pierre'],
            ['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 100],
        ]);

        $picked = ResourceService::pickExhausted($plan, $this->rows('pierre1', 'pierre1', 'arbre1'), 2);

        $this->assertSame([], $picked, 'gelé : les tentatives stériles ont mangé le budget');
    }

    /**
     * GELÉ — le budget est celui du DERNIER rendement seulement.
     *
     * L'appelant tirait `$rand` dans la boucle des rendements et le relisait
     * après : avec du bois (5 unités) puis de la pierre (1), le budget vaut 1.
     * Ce test décrit le budget qu'il reçoit, pas celui qu'il devrait recevoir.
     */
    public function testABudgetOfOneExhaustsOnlyOneVein(): void
    {
        ResourceService::setDiceForTests(new ScriptedDice([[1]]));

        $plan = $this->planWith([['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 100]]);

        $picked = ResourceService::pickExhausted($plan, $this->rows('arbre1', 'arbre1', 'arbre1'), 1);

        $this->assertCount(1, $picked);
    }

    /**
     * GELÉ, ET FAUX — un budget NUL épuise quand même un filon.
     *
     * Le budget est éprouvé APRÈS la tentative, donc une fouille qui n'a rien
     * rapporté peut tarir une veine. C'est le cas du joueur qui fouille une
     * case sans rendement déclaré.
     */
    public function testAnEmptyBudgetStillExhaustsOneVein(): void
    {
        ResourceService::setDiceForTests(new ScriptedDice([[1]]));

        $plan = $this->planWith([['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 100]]);

        $this->assertCount(
            1,
            ResourceService::pickExhausted($plan, $this->rows('arbre1'), 0),
            'gelé : rien récolté, un filon tari quand même'
        );
    }

    /**
     * VOULU — mais décalé d'une unité : « exhaust > 1d100 » donne
     * exhaust - 1 chances sur cent. Un taux annoncé à 75 vaut 74 %.
     */
    public function testExhaustThresholdIsOffByOne(): void
    {
        $plan = $this->plan(['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]);

        ResourceService::setDiceForTests(new ScriptedDice([[74]]));
        $depleted = [];
        ResourceService::createExhaustArray($plan, $depleted, $this->row());
        $this->assertSame([42], $depleted, 'un dé de 74 épuise face à un taux de 75');

        ResourceService::setDiceForTests(new ScriptedDice([[75]]));
        $intact = [];
        ResourceService::createExhaustArray($plan, $intact, $this->row());
        $this->assertSame([], $intact, 'un dé de 75 n\'épuise PAS face à un taux de 75 — le seuil est strict');
    }

    /**
     * VOULU — la repousse se tire sur mille, l'épuisement sur cent. Un
     * regrow de 20 vaut donc 1,9 % par passage du cron, pas 20 % : la
     * repousse doit être lente. L'asymétrie est délibérée.
     */
    public function testRegrowRollsOnAThousandNotAHundred(): void
    {
        $plan = $this->plan(['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]);

        ResourceService::setDiceForTests(new ScriptedDice([[19]]));
        $regrown = [];
        ResourceService::createRegrowArray($plan, $regrown, $this->row());
        $this->assertSame([42], $regrown, 'un dé de 19 fait repousser face à un taux de 20');

        // Un dé de 100 ne repousse pas : impossible si l'échelle était le cent.
        ResourceService::setDiceForTests(new ScriptedDice([[100]]));
        $still = [];
        ResourceService::createRegrowArray($plan, $still, $this->row());
        $this->assertSame([], $still, 'l\'échelle est le mille : 20 ne bat pas 100');
    }

    /**
     * PAS VOULU — 41 entrées de biome des plans réels n'ont ni exhaust ni
     * regrow. « null > n » étant toujours faux, ces ressources ne s'épuisent
     * jamais ET ne repoussent jamais : 3 476 lignes en production, une ferme
     * inépuisable que personne n'a décidée.
     *
     * Gelé ici pour que le passage des taux au catalogue ne leur donne pas
     * l'épuisement en silence — ce serait un changement d'équilibrage, pas
     * une migration.
     */
    public function testBiomeWithoutRatesNeverExhaustsNorRegrows(): void
    {
        $plan = $this->plan(['wall' => 'arbre1', 'ressource' => 'bois']);

        // Le dé le plus favorable qui soit, dans les deux sens.
        ResourceService::setDiceForTests(new ScriptedDice([[1]]));
        $depleted = [];
        ResourceService::createExhaustArray($plan, $depleted, $this->row());
        $this->assertSame([], $depleted, 'sans exhaust, la ressource ne s\'épuise jamais');

        ResourceService::setDiceForTests(new ScriptedDice([[1]]));
        $regrown = [];
        ResourceService::createRegrowArray($plan, $regrown, $this->row());
        $this->assertSame([], $regrown, 'sans regrow, la ressource ne repousse jamais');
    }

    /** Un type absent du biome du plan est ignoré : ni épuisement, ni repousse. */
    public function testResourceAbsentFromBiomeIsUntouched(): void
    {
        $plan = $this->plan(['wall' => 'pierre1', 'ressource' => 'pierre', 'exhaust' => 100, 'regrow' => 1000]);

        ResourceService::setDiceForTests(new ScriptedDice([[1]]));
        $depleted = [];
        ResourceService::createExhaustArray($plan, $depleted, $this->row());
        $this->assertSame([], $depleted, 'arbre1 n\'est pas au biome de ce plan');
    }

    /** Un plan sans clé biomes du tout ne fait rien, sans erreur. */
    public function testPlanWithoutBiomesIsInert(): void
    {
        $plan = (object) [];

        $depleted = [];
        ResourceService::createExhaustArray($plan, $depleted, $this->row());
        $this->assertSame([], $depleted);

        $regrown = [];
        ResourceService::createRegrowArray($plan, $regrown, $this->row());
        $this->assertSame([], $regrown);
    }
}
