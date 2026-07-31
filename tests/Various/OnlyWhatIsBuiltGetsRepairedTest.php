<?php

namespace Tests\Various;

use App\Entity\BuildingType;
use App\Entity\PlantType;
use App\Entity\ResourceType;
use App\Entity\SceneryType;
use App\Entity\StructureType;
use PHPUnit\Framework\TestCase;

/**
 * Ce qui se répare est une propriété du TYPE, pas de la catégorie.
 *
 * `reparer` ne filtrait que par catégorie — `TargetType: structure`. C'était
 * juste tant que les seules structures étaient bâties ; ressources, décors et
 * plantes sont devenus des entités et sont tombés du même côté de l'arbre. On
 * pouvait réparer une fleur, et y laisser une action.
 *
 * La catégorie ne peut pas trancher : elle n'a que deux valeurs, et les deux
 * sont justes. Ces cas épinglent où la réponse vit désormais.
 */
class OnlyWhatIsBuiltGetsRepairedTest extends TestCase
{
    /**
     * Les cas qui touchent une vraie ligne — ou un objet legacy, qui ouvre une
     * connexion dès sa naissance — ont besoin du socle. Ailleurs, on saute.
     */
    private function bootstrapOrSkip(): \Doctrine\DBAL\Connection
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            $conn = \App\Entity\EntityManagerFactory::getEntityManager()->getConnection();
            $conn->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database unreachable: ' . $e->getMessage());
        }

        return $conn;
    }

    /**
     * Ce qui a été DRESSÉ par quelqu'un s'entretient ; ce qui pousse ou gît
     * là, non — son cycle est l'épuisement puis la repousse.
     */
    public function testWhatWasErectedIsRepairableByDefault(): void
    {
        $this->assertTrue((new BuildingType())->isRepairable(), 'un édifice s\'entretient');
        $this->assertTrue((new SceneryType())->isRepairable(), 'une statue, une clôture aussi');

        $this->assertFalse((new PlantType())->isRepairable(), 'on ne répare pas une fleur');
        $this->assertFalse((new ResourceType())->isRepairable(), 'ni un rocher');
    }

    /**
     * Le type peut contredire sa famille — dans les deux sens.
     *
     * C'est la raison d'être de la colonne : une palissade de décor qu'on veut
     * pouvoir rafistoler ne doit pas obliger à déplacer toute une famille.
     */
    public function testATypeCanOverrideItsFamilyBothWays(): void
    {
        $resource = (new ResourceType())->setRepairable(true);
        $this->assertTrue($resource->isRepairable(), 'un puits de pierre taillée, pourquoi pas');

        $building = (new BuildingType())->setRepairable(false);
        $this->assertFalse($building->isRepairable(), 'une ruine se visite, elle ne se relève pas');
    }

    /**
     * Rien de décidé = la FAMILLE répond, et on peut le relire tel quel.
     *
     * L'écran de réglage a besoin de la nuance : proposer « oui / non » sans
     * troisième choix couperait le type de sa famille dès la première
     * sauvegarde, sans que personne l'ait demandé.
     */
    public function testUndecidedStaysUndecidedAndFollowsTheFamily(): void
    {
        $plant = new PlantType();

        $this->assertNull($plant->getRepairableOverride(), 'rien n\'a été décidé sur ce type');
        $this->assertFalse($plant->repairableFamilyDefault());

        $plant->setRepairable(true);
        $this->assertTrue($plant->getRepairableOverride());

        $plant->setRepairable(null);
        $this->assertNull($plant->getRepairableOverride(), 'on peut rendre la décision à la famille');
        $this->assertFalse($plant->isRepairable());
    }

    /**
     * Un OBJET POSÉ reste réparable, et c'est le cas qui a failli passer.
     *
     * Un objet unique — coffre, arme lâchée — est une entité SANS type au
     * catalogue : `UniqueObjectService` l'inscrit sous la race « objet », qui
     * n'existe dans `races` pour personne. Une garde qui se contente
     * d'interroger le catalogue rend donc `null`, refuse, et retire en silence
     * une capacité que ces objets avaient.
     *
     * C'est aussi le seul endroit du jeu où réparer un OBJET a déjà un sens :
     * posé sur le plateau, il porte sa durabilité comme un édifice ses PV.
     */
    public function testADroppedObjectStaysRepairable(): void
    {
        $condition = new \App\Action\Condition\RequiresRepairableTargetCondition();

        $this->bootstrapOrSkip();

        $verdict = function (string $playerType, string $race) use ($condition): bool {
            $actor = new \Classes\Player(1);
            $target = new \Classes\Player(2);
            /* On fabrique la seule chose que la garde regarde, sans base :
             * ce que porte la ligne `players`. */
            $target->data = (object) ['player_type' => $playerType, 'race' => $race];

            return $condition->check(
                $actor,
                $target,
                new \App\Entity\ActionCondition(),
                new \App\Action\Condition\ConditionObject()
            )->isSuccess();
        };

        $this->assertTrue(
            $verdict('unique', \App\Service\UniqueObjectService::ITEM_RACE),
            'un coffre, une arme lâchée : posés, ils se réparent'
        );

        /* La même race SANS être un objet posé ne passe pas : c'est bien le
         * player_type qui ouvre la porte, pas une chaîne magique. */
        $this->assertFalse($verdict('scenery', \App\Service\UniqueObjectService::ITEM_RACE));
    }

    /** La capacité est portée par les choses POSÉES, pas par les peuples. */
    public function testRepairabilityBelongsToPlacedThings(): void
    {
        $this->assertInstanceOf(StructureType::class, new BuildingType());
        $this->assertFalse(
            method_exists(\App\Entity\CharacterRace::class, 'isRepairable'),
            'un personnage se soigne, il ne se répare pas'
        );
    }

    /**
     * L'action porte la condition, AVANT le coût et en contexte d'affichage.
     *
     * L'ordre n'est pas cosmétique : posée après `RequiresTraitValue`
     * (ordre 3), l'action serait facturée avant d'être refusée. Et sans
     * `display_context`, le bouton s'afficherait sur une fleur pour échouer au
     * clic — une affordance qui ment.
     */
    public function testReparerCarriesTheConditionBeforeItsCost(): void
    {
        $conn = $this->bootstrapOrSkip();

        $rows = $conn->fetchAllAssociative(
            "SELECT conditionType, execution_order, blocking, display_context
               FROM action_conditions
              WHERE action_id = (SELECT id FROM actions WHERE name = 'reparer')"
        );

        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['conditionType']] = $row;
        }

        $this->assertArrayHasKey(
            'RequiresRepairableTarget',
            $byType,
            'sans elle, toute structure redevient réparable'
        );

        $guard = $byType['RequiresRepairableTarget'];
        $this->assertSame(1, (int) $guard['blocking']);
        $this->assertSame(1, (int) $guard['display_context'], 'le bouton doit disparaître, pas échouer');

        if (isset($byType['RequiresTraitValue'])) {
            $this->assertLessThan(
                (int) $byType['RequiresTraitValue']['execution_order'],
                (int) $guard['execution_order'],
                'refuser AVANT de facturer l\'action'
            );
        }
    }
}
