<?php

namespace Tests\Player;

use App\Service\ItemInstanceService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Seuil d'état d'une demande d'achat.
 *
 * Une demande porte un objet de CATALOGUE — « je cherche une épée » —
 * et son auteur bloque son or à l'avance. Depuis que les exemplaires
 * usés circulent, y répondre pourrait vouloir dire livrer une épée à
 * 3/20 sans que l'acheteur ait son mot à dire. Il déclare donc le PIRE
 * état qu'il accepte, et c'est cette règle qui est épinglée ici.
 */
#[Group('items-golden-master')]
class AskConditionGoldenMasterTest extends TestCase
{
    public function testAStackAlwaysSatisfiesEveryThreshold(): void
    {
        // Une pile n'a pas de durabilité : elle est intacte par
        // construction, donc éligible même au palier le plus strict.
        foreach (array_keys(ItemInstanceService::CONDITION_LEVELS) as $level) {
            $this->assertTrue(
                ItemInstanceService::meetsCondition(null, null, $level),
                "une pile satisfait le palier {$level}"
            );
        }
    }

    public function testBrandNewOnlyAcceptsAnIntactInstance(): void
    {
        $this->assertTrue(ItemInstanceService::meetsCondition(20, 20, 100));
        $this->assertFalse(ItemInstanceService::meetsCondition(19, 20, 100), 'une éraflure suffit à refuser');
    }

    public function testGoodConditionMatchesTheGreenBandOfTheDisplay(): void
    {
        // 50 % est la frontière de la bande verte de stateLine : ce que
        // le joueur VOIT comme « bon état » est ce qu'il obtient.
        $this->assertTrue(ItemInstanceService::meetsCondition(10, 20, 50));
        $this->assertFalse(ItemInstanceService::meetsCondition(9, 20, 50));
    }

    public function testWorstLevelAcceptsAlmostAnythingButNeverABrokenItem(): void
    {
        $this->assertTrue(ItemInstanceService::meetsCondition(1, 20, 1), 'très abîmé, mais utilisable');
        $this->assertFalse(
            ItemInstanceService::meetsCondition(0, 20, 1),
            'un objet brisé ne contribue plus rien : aucun palier ne l\'accepte'
        );
    }

    public function testAnAskWithoutThresholdConstrainsNothing(): void
    {
        // Les demandes ouvertes AVANT la migration portent 0 : leur sens
        // est « rien n'était vérifié », pas « rien n'est acceptable ».
        $this->assertTrue(ItemInstanceService::meetsCondition(0, 20, 0));
        $this->assertTrue(ItemInstanceService::meetsCondition(1, 20, 0));
    }

    public function testTheThreeLevelsAreOrderedFromStrictestToLoosest(): void
    {
        $levels = array_keys(ItemInstanceService::CONDITION_LEVELS);

        $this->assertSame([100, 50, 1], $levels, 'l\'ordre est celui présenté au joueur');

        // Un exemplaire à 60 % : accepté par les deux paliers les plus
        // souples, refusé par « neuf ». La monotonie est ce qui rend le
        // choix compréhensible.
        $this->assertFalse(ItemInstanceService::meetsCondition(12, 20, 100));
        $this->assertTrue(ItemInstanceService::meetsCondition(12, 20, 50));
        $this->assertTrue(ItemInstanceService::meetsCondition(12, 20, 1));
    }
}
