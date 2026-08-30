<?php

namespace App\Service;

use Classes\Player;

/**
 * The spells an equipped item lends its bearer (`items.spell`).
 *
 * They are never written to `players_actions`: the grant is READ from what
 * the player wears, every time. Two consequences, both wanted — the spell
 * leaves with the item (dropped, banked, broken, traded, looted from a
 * corpse) with nothing to clean up, and it cannot count toward
 * NUMBER_MAX_COMP, which is a count of learned rows. A borrowed spell comes
 * on top of the cap.
 *
 * The name is resolved against the actions catalogue in DB: an item naming
 * a spell that no longer exists lends nothing, rather than fataling on the
 * item line the way the old JSON lookup did.
 */
final class ItemGrantedSpellService
{
    private ActionService $actionService;

    public function __construct(?ActionService $actionService = null)
    {
        $this->actionService = $actionService ?? new ActionService();
    }

    /**
     * Spell name => label of the item lending it. Deduplicated: two torches,
     * or a torch and a wand carrying the same spell, lend it once.
     *
     * @return array<string, string>
     */
    public function forPlayer(Player $player): array
    {
        $catalogue = $this->actionService->getCastableSpellNames();
        $granted = [];

        /* The equipped list is a JOIN of players_items and items, so the
           catalogue columns are on the row already — no get_data() needed. */
        foreach ($player->getEquipedItems() as $row) {
            $spell = (string) ($row->spell ?? '');
            if ($spell === '' || !array_key_exists($spell, $catalogue)) {
                continue;
            }
            $granted[$spell] ??= (string) ($row->name ?? $spell);
        }

        return $granted;
    }

    /**
     * Names only, for unioning into the player's usable actions.
     *
     * @return list<string>
     */
    public function namesForPlayer(Player $player): array
    {
        return array_keys($this->forPlayer($player));
    }
}
