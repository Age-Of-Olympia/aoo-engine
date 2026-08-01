<?php

namespace App\Service;

use App\Interface\OwnsCaracsInterface;
use App\Factory\EntityManagerFactory;
use App\Entity\Item;

/**
 * The stat block an entity gets from its type, whichever catalogue holds it.
 *
 * Entities take their type from `races`, items from `items`. Which table to
 * read is the only thing decided here; what the type gives is answered by the
 * type itself, through {@see \App\Interface\OwnsCaracsInterface}. Callers pass their
 * discriminator and get a block, without knowing either catalogue exists.
 *
 * Returns null for a type the catalogue does not know, so the caller can warn
 * and fall back to zeros rather than get a silently empty block.
 */
final class EntityTypeCaracsService
{
    /** `players.player_type` of an entity that IS an item exemplar. */
    public const ITEM_TYPE = 'item';

    public function ownCaracs(?string $playerType, string $typeName): ?object
    {
        if ($typeName === '') {
            return null;
        }

        if ($playerType === self::ITEM_TYPE) {
            $item = EntityManagerFactory::getEntityManager()
                ->getRepository(Item::class)
                ->findOneBy(['name' => $typeName]);

            return $item === null ? null : (object) $item->ownCaracs();
        }

        $race = (new RaceService())->getRaceData($typeName);

        return is_object($race) ? $race : null;
    }
}
