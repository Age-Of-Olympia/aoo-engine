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

    /**
     * Each worn item once, keyed by its first slot in $slots order: a
     * two-handed item fills main1 and main2 with the same object.
     *
     * @param list<string> $slots
     * @return array<string, object>
     */
    public static function wornItems(object $emplacements, array $slots = ITEM_EMPLACEMENT_FORMAT): array
    {
        $items = [];
        foreach ($slots as $slot) {
            $item = $emplacements->{$slot} ?? null;
            if ($item !== null && !in_array($item, $items, true)) {
                $items[$slot] = $item;
            }
        }

        return $items;
    }

    public static function isTwoHanded(object $item): bool
    {
        return (string) ($item->data->emplacement ?? '') === 'deuxmains';
    }
}
