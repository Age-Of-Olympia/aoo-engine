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
     * Coordonnées validées de la case ORIGINE choisie, ou null quand le
     * choix est absent, illisible (non numérique) ou refusé.
     *
     * La règle est portée par l'EMPRISE : une case de la forme bâtie doit
     * toucher le bâtisseur (distance ≤ 1) — pour un édifice 2×2, l'origine
     * peut donc se poser à deux cases. La liberté de CHAQUE case reste le
     * travail de place(), qui verrouille et refuse en nommant la case.
     *
     * @param string|null $type type de structure en cours de construction —
     *        null : emprise d'une case (règle historique)
     */
    public static function resolve(object $actorCoords, ?string $type = null): ?object
    {
        if (!self::requested()) {
            return null;
        }
        if (!is_numeric($_POST['buildX']) || !is_numeric($_POST['buildY'])) {
            // (int)'abc' vaudrait 0 : du bruit ne doit pas résoudre en (0,0).
            return null;
        }

        $x = (int) $_POST['buildX'];
        $y = (int) $_POST['buildY'];

        $offsets = [[0, 0]];
        if ($type !== null) {
            $footprint = (new \App\Service\Map\EntityTypeFootprintService())->catalogue()[$type] ?? null;
            if ($footprint !== null) {
                $offsets = array_values($footprint->offsets());
            }
        }

        $touches = false;
        foreach ($offsets as [$dx, $dy]) {
            if (max(abs(($x + $dx) - (int) $actorCoords->x), abs(($y + $dy) - (int) $actorCoords->y)) <= 1) {
                $touches = true;
                break;
            }
        }

        $taken = View::get_coords_taken(clone $actorCoords);

        if (!$touches || in_array($x . ',' . $y, $taken, true)) {
            return null;
        }

        $goCoords = clone $actorCoords;
        $goCoords->x = $x;
        $goCoords->y = $y;

        return $goCoords;
    }

    /** Message de refus commun aux deux consommateurs. */
    public const REFUSAL = 'Impossible de construire là — l\'édifice doit toucher son bâtisseur, sur des cases libres.';
}
