<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Service\ItemInstanceService;
use Doctrine\DBAL\Connection;

/**
 * Ce qui obstrue une case, quel que soit le catalogue d'où vient le type.
 *
 * Les deux questions — barre-t-il le pas ? arrête-t-il la flèche ? — se posaient
 * jusqu'ici aux seules races. Un exemplaire POSÉ occupe pourtant une case comme
 * un mur, et son type vit dans `items` : faute de l'y chercher, il obstruait par
 * accident de recherche, absent des deux listes.
 *
 * Les deux jeux de noms restent SÉPARÉS, et c'est le discriminant qui choisit.
 * Les mélanger paraissait sans risque — un coffre existe des deux côtés et
 * répond la même chose — mais dès que l'un des deux change, les homonymes
 * divergent et un nom seul ne dit plus de quel catalogue il vient. Le
 * `player_type`, lui, le sait.
 *
 * Ne dit rien de ce qui TRAÎNE : `slot` a déjà écarté le butin au sol avant
 * qu'on en arrive au type.
 *
 * Sans mémoire propre, à dessein : `RaceService` garde déjà la sienne, la
 * requête objets tient en cent trente-sept lignes, et un cache de plus ici
 * servait surtout à ignorer un type qu'on venait de modifier.
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

    /** `players.player_type` d'une entité qui EST un exemplaire d'objet. */
    public const ITEM_TYPE = ItemInstanceService::ENTITY_TYPE;

    /**
     * Les types qu'on TRAVERSE, par catalogue.
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
     * Les types qui arrêtent la flèche, par catalogue.
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
