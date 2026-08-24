<?php

namespace App\Service\Map;

use Classes\Item;
use Classes\Player;

/**
 * Ce qu'il faut porter pour passer : « item:nom:n,spell:nom ».
 *
 * La règle vient du déclencheur `need`, qui la gardait pour lui. Le
 * téléporteur en a besoin aussi : un `need` et un `tp` posés sur la même case
 * font une porte gardée, or l'éditeur ne montre qu'un déclencheur par case
 * depuis que la couche se peint. Plutôt que d'obliger l'animateur à empiler
 * deux objets, `tp` accepte la condition en dernier paramètre — et la lit
 * ici, à l'identique.
 *
 * `need` reste : toutes les portes gardées ne téléportent pas.
 */
final class TriggerRequirements
{
    /** Ce que voit le joueur quand il lui manque quelque chose. */
    public const REFUSAL = 'Le passage reste clos.';

    /**
     * Le joueur satisfait-il toutes les conditions ?
     *
     * Une chaîne vide n'exige rien. Un terme inconnu est ignoré plutôt que
     * refusé : une condition mal tapée ne doit pas murer une case.
     *
     * @param string $params « item:pomme:3,spell:feu » — les termes se
     *        cumulent, il les faut tous
     */
    public static function met(Player $player, string $params): bool
    {
        foreach (explode(',', $params) as $term) {
            $parts = explode(':', trim($term));

            if ($parts[0] === 'item') {
                $wanted = (int) (($parts[2] ?? '') !== '' ? $parts[2] : 1);

                if ((new Item($parts[1] ?? ''))->get_n($player) < $wanted) {
                    return false;
                }

                continue;
            }

            if ($parts[0] === 'spell' && !$player->have_spell($parts[1] ?? '')) {
                return false;
            }
        }

        return true;
    }
}
