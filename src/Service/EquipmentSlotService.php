<?php

namespace App\Service;

/**
 * Maps an item's catalogue emplacement to the player's physical hand slots.
 * A two-handed item's catalogue value describes the item; its worn location
 * is main1, and main2 is occupied by that same item.
 */
final class EquipmentSlotService
{
    /** The persisted equipment location for a catalogue emplacement. */
    public static function storageSlot(string $emplacement): string
    {
        return $emplacement === 'deuxmains' ? 'main1' : $emplacement;
    }

    /** @return list<string> The slots that must be free before equipping this item. */
    public static function slotsToClear(string $emplacement): array
    {
        if ($emplacement === 'deuxmains') {
            /* Include deuxmains to clear records written by older versions. */
            return ['main1', 'main2', 'deuxmains'];
        }

        if ($emplacement === 'main1' || $emplacement === 'main2') {
            return [$emplacement, 'deuxmains'];
        }

        return [$emplacement];
    }

    public static function isTwoHanded(object $item): bool
    {
        return (string) ($item->data->emplacement ?? '') === 'deuxmains';
    }
}
