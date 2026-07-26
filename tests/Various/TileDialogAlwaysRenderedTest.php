<?php

namespace Tests\Various;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Deux sources de parole coexistent sur une case, et elles n'ont pas la
 * même nature :
 *
 *  - le déclencheur `map_dialogs` est collé à la CASE. Il survit à ce
 *    qui s'y trouve, et se lit d'un regard (bulle rendue dans le
 *    panneau d'observation) ;
 *  - `buildings.dialog` est porté par l'ENTITÉ. Il la suit — ruine =
 *    muet, suppression = disparu — et s'obtient en s'adressant à elle
 *    (bouton « Parler », adjacence exigée).
 *
 * Le rendu du premier vivait dans la branche « aucune entité sur la
 * case ». C'était sans conséquence tant que rien n'occupait les cases,
 * mais depuis que les structures sont des entités, une pancarte masque
 * son propre texte : le bâtiment prend la branche haute, et le
 * déclencheur n'est jamais lu.
 *
 * Ce test épingle la structure du contrôleur, faute de pouvoir jouer
 * une requête HTTP complète ici : le bloc de dialogue de case doit
 * rester HORS de la branche conditionnelle des entités.
 */
#[Group('observe')]
class TileDialogAlwaysRenderedTest extends TestCase
{
    private function source(): string
    {
        $source = file_get_contents(__DIR__ . '/../../observe.php');
        $this->assertIsString($source);

        return $source;
    }

    public function testTheTileDialogIsReadOutsideTheEntityBranch(): void
    {
        $source = $this->source();

        $entityBranch = strpos($source, 'EntityCardView::render');
        $tileDialog = strpos($source, 'TileDialogView::render');

        $this->assertIsInt($entityBranch);
        $this->assertIsInt($tileDialog);
        $this->assertGreaterThan(
            $entityBranch,
            $tileDialog,
            'le dialogue de case se rend après la branche des entités, pas dedans'
        );

        /* La preuve structurelle : à la colonne zéro, donc au niveau du
         * fichier — pas indenté dans un bloc conditionnel. */
        $this->assertMatchesRegularExpression(
            '/^\\\\App\\\\View\\\\Observe\\\\TileDialogView::render\(/m',
            $source,
            'un déclencheur collé à la case se lit quoi qu\'il y ait dessus'
        );
    }

    /**
     * Le dialogue porté par l'entité, lui, reste une affordance de la
     * fiche : les deux mécanismes ne doivent pas fusionner par accident.
     */
    public function testTheEntityDialogStaysOnTheEntityCard(): void
    {
        $card = file_get_contents(__DIR__ . '/../../src/View/Observe/EntityCardView.php');
        $this->assertIsString($card);

        $this->assertStringContainsString(
            'getDialog()',
            $card,
            'le dialogue porté par un bâtiment reste rendu par sa fiche'
        );
    }
}
