<?php

namespace Tests\Various;

use App\Service\Map\TriggerRequirements;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'il faut porter pour passer, partagé par `need` et par `tp`.
 *
 * Un `need` et un `tp` sur la même case font une porte gardée. L'éditeur
 * n'affiche qu'un déclencheur par case depuis que la couche se peint, donc le
 * téléporteur prend la condition en cinquième paramètre plutôt que d'obliger
 * à empiler deux objets. La règle ne devait pas être réécrite pour autant :
 * une porte gardée doit refuser pareil, qu'elle téléporte ou non.
 *
 * `need` reste utile seul — toutes les portes gardées ne mènent pas ailleurs.
 */
class TriggerRequirementsTest extends TestCase
{
    /** Sans condition, rien à satisfaire : le passage est libre. */
    public function testAnEmptyConditionAsksForNothing(): void
    {
        $player = $this->player([], []);

        $this->assertTrue(TriggerRequirements::met($player, ''));
    }

    /** Un sort manquant ferme le passage, un sort porté l'ouvre. */
    public function testASpellIsRequired(): void
    {
        $this->assertFalse(TriggerRequirements::met($this->player([], []), 'spell:feu'));
        $this->assertTrue(TriggerRequirements::met($this->player([], ['feu']), 'spell:feu'));
    }

    /** Les termes se cumulent : il les faut tous. */
    public function testEveryTermMustHold(): void
    {
        $player = $this->player([], ['feu']);

        $this->assertFalse(
            TriggerRequirements::met($player, 'spell:feu,spell:glace'),
            'un seul des deux sorts ne suffit pas'
        );
        $this->assertTrue(TriggerRequirements::met($player, 'spell:feu,spell:feu'));
    }

    /**
     * Un terme inconnu est ignoré, pas refusé : une faute de frappe dans un
     * paramètre ne doit pas murer une case.
     */
    public function testAnUnknownTermDoesNotWallTheCell(): void
    {
        $this->assertTrue(TriggerRequirements::met($this->player([], []), 'sortilege:feu'));
    }

    /**
     * La condition d'un `tp` survit à ses virgules.
     *
     * tp.php lit « x,y,z,plan[,condition] » avec explode(..., 5) : tout ce qui
     * suit le quatrième séparateur est la condition, virgules comprises.
     */
    public function testTheConditionOfATpKeepsItsCommas(): void
    {
        $parts = explode(',', 'x,y,-1,nidhogg,item:clef:1,spell:feu', 5);

        $this->assertSame('nidhogg', $parts[3], 'le plan reste en quatrième');
        $this->assertSame('item:clef:1,spell:feu', $parts[4], 'la condition reste entière');

        $legacy = explode(',', 'x,y,-1,nidhogg', 5);
        $this->assertArrayNotHasKey(4, $legacy, 'un tp d\'avant n\'exige rien');
    }

    /**
     * Un joueur de fixture : ses sorts, et rien d'autre.
     *
     * `Item::get_n()` interroge la base ; les cas ci-dessus n'en ont pas
     * besoin, la condition d'objet est couverte par les tests d'inventaire.
     *
     * @param list<string> $items  inutilisé ici, gardé pour la lisibilité
     * @param list<string> $spells
     */
    private function player(array $items, array $spells): \Classes\Player
    {
        return new class ($spells) extends \Classes\Player {
            /** @param list<string> $spells */
            public function __construct(private array $spells)
            {
            }

            public function have_spell($name): bool
            {
                return in_array($name, $this->spells, true);
            }
        };
    }
}
