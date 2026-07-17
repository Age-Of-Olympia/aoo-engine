<?php
namespace App\Action\Condition;

use Classes\View;

/**
 * Résolution UNIQUE de la case de construction choisie par le joueur
 * (js/build_picker.js → POST buildX/buildY) : adjacente ET libre —
 * exactement les cases .go que le masque a proposées.
 *
 * Partagée entre BuildSiteCondition (validation bloquante, avant tout
 * paiement) et PlaceStructureOutcomeInstruction (consommation) : une
 * seule source de vérité, la règle ne peut pas dériver entre les deux.
 */
final class BuildSitePick
{
    /** Le joueur a-t-il choisi une case (mode picker) ? */
    public static function requested(): bool
    {
        return isset($_POST['buildX'], $_POST['buildY']);
    }

    /**
     * Coordonnées validées de la case choisie, ou null quand le choix est
     * absent, illisible (non numérique) ou refusé (ni adjacente ni libre).
     */
    public static function resolve(object $actorCoords): ?object
    {
        if (!self::requested()) {
            return null;
        }
        if (!is_numeric($_POST['buildX']) || !is_numeric($_POST['buildY'])) {
            // (int)'abc' vaudrait 0 : du bruit ne doit pas résoudre en (0,0).
            return null;
        }

        $requested = ((int) $_POST['buildX']) . ',' . ((int) $_POST['buildY']);
        $around = View::get_coords_arround(clone $actorCoords, 1);
        $taken = View::get_coords_taken(clone $actorCoords);

        if (!in_array($requested, $around, true) || in_array($requested, $taken, true)) {
            return null;
        }

        $goCoords = clone $actorCoords;
        $goCoords->x = (int) $_POST['buildX'];
        $goCoords->y = (int) $_POST['buildY'];

        return $goCoords;
    }

    /** Message de refus commun aux deux consommateurs. */
    public const REFUSAL = 'Impossible de construire là — la case doit être adjacente et libre.';
}
