<?php

namespace App\Service\Map;

use Classes\Item;
use Classes\Player;

/**
 * What a player must carry to pass: "item:name:n,spell:name".
 *
 * Read by the `need` trigger and by `tp`, whose fifth parameter holds the
 * same syntax. Terms are cumulative; an unknown term is ignored.
 */
final class TriggerRequirements
{
    /** Shown to the player when a term is not satisfied. */
    public const REFUSAL = 'Le passage reste clos.';

    /**
     * @param string $params comma-separated terms, all of them required
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
