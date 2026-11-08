<?php

namespace App\Service\Map;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one place that puts a character (player, PNJ, god, tutorial
 * character) on another cell. Every move reads the cell left and the cell
 * reached here, so what depends on crossing from one to the other (biome
 * entry and exit) has a single place to hook in. Objects move through
 * {@see EntityLocationService}; a guard test keeps both the only writers.
 *
 * It writes the cell and the footprint, nothing else: what a step does on
 * arrival (elements, followers, board refresh) stays with the caller.
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
     * @return array{from: ?int, to: ?int} coords ids: the cell left and the cell reached (null: off the board)
     */
    public function move(int $characterId, ?int $coordsId): array
    {
        $from = $this->db->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$characterId]);
        $moved = ['from' => $from === false || $from === null ? null : (int) $from, 'to' => $coordsId];

        // A preview (action simulation) never moves anyone
        if (SimulationGuard::blocksWrite()) {
            return $moved;
        }
        $this->db->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $characterId]);
        (new EntityCellService($this->db))->syncCells($characterId);

        return $moved;
    }
}
