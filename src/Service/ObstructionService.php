<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Service\ItemInstanceService;
use Doctrine\DBAL\Connection;

/**
 * What obstructs a tile, from either type catalogue.
 *
 * Both catalogues answer, and the sets stay separate: a name alone does not
 * say which one it came from, so callers select by `players.player_type`.
 */
final class ObstructionService extends BaseService
{
    private Connection $conn;
    private RaceService $raceService;

    public function __construct(?Connection $conn = null, ?RaceService $raceService = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->raceService = $raceService ?? new RaceService();
    }

    /** `players.player_type` of an entity that IS an item exemplar. */
    public const ITEM_TYPE = ItemInstanceService::ENTITY_TYPE;

    /**
     * Types one walks through, per catalogue.
     *
     * @return array{race: list<string>, item: list<string>}
     */
    public function passableTypeNames(): array
    {
        return [
            'race' => $this->raceService->getPassableStructureNames(),
            'item' => $this->conn->fetchFirstColumn('SELECT name FROM items WHERE blocks_passage = 0'),
        ];
    }

    /**
     * Types that stop an arrow, per catalogue.
     *
     * @return array{race: list<string>, item: list<string>}
     */
    public function projectileBlockingTypeNames(): array
    {
        return [
            'race' => $this->raceService->getProjectileBlockingRaceNames(),
            'item' => $this->conn->fetchFirstColumn('SELECT name FROM items WHERE blocks_projectiles = 1'),
        ];
    }
}
