<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one place that puts a character (player, PNJ, god, tutorial
 * character) on another cell. Every move reads the cell left and the cell
 * reached here, so biome entry and exit ({@see BiomeCrossingService}) see
 * every crossing. Objects move through {@see EntityLocationService}; a
 * guard test keeps both the only writers.
 *
 * It writes the cell and the footprint and crosses the biomes, nothing
 * else: what a step does on arrival (elements, followers, board refresh)
 * stays with the caller.
 */
final class CharacterMoveService
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * @param int|null $coordsId null takes the character off the board, as the callers' unchecked lookups always did
     * @return array{from: ?int, to: ?int, messages: list<string>} coords ids of the cell left and the cell reached
     *     (null: off the board), and what the biomes crossed told the character
     */
    public function move(int $characterId, ?int $coordsId, BiomeEvents $biomeEvents = BiomeEvents::Cross): array
    {
        $from = $this->db->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$characterId]);
        $moved = ['from' => $from === false || $from === null ? null : (int) $from, 'to' => $coordsId, 'messages' => []];

        // A preview (action simulation) never moves anyone
        if (SimulationGuard::blocksWrite()) {
            return $moved;
        }
        $this->db->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $characterId]);
        (new EntityCellService($this->db))->syncCells($characterId);

        $moved['messages'] = match ($biomeEvents) {
            BiomeEvents::Cross => (new BiomeCrossingService($this->db))->cross($characterId, $moved['from'], $coordsId),
            BiomeEvents::Arrive => (new BiomeCrossingService($this->db))->cross($characterId, null, $coordsId),
            BiomeEvents::None => [],
        };

        return $moved;
    }
}
