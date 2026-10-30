<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Service\Map\BoardChanges;
use App\Service\Map\EntityCellService;
use Classes\Player;
use Classes\View;
use Doctrine\DBAL\Connection;

/** Admin roster and lifecycle operations for prayable gods. */
final class CultAdminService
{
    private Connection $connection;

    public function __construct()
    {
        $this->connection = EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<string> */
    public function plans(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            'SELECT DISTINCT plan FROM coords ORDER BY plan'
        ));
    }

    /** @return list<array{id:int,name:string,race:string}> */
    public function cultCandidates(bool $includeOtherRaces = false): array
    {
        $raceFilter = $includeOtherRaces ? '' : " AND p.race = 'dieu'";
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'race' => (string) $row['race'],
        ], $this->connection->fetchAllAssociative(
            "SELECT p.id, p.name, p.race
               FROM players p
              WHERE p.player_type = 'npc'{$raceFilter}
                AND NOT EXISTS (
                    SELECT 1 FROM players_options o
                     WHERE o.player_id = p.id AND o.name = 'prayable'
                )
              ORDER BY p.name, p.id"
        ));
    }

    public function addCult(int $npcId): void
    {
        $npc = $this->connection->fetchAssociative(
            "SELECT p.id, p.name
               FROM players p
              WHERE p.id = ? AND p.player_type = 'npc'
                AND NOT EXISTS (
                    SELECT 1 FROM players_options o
                     WHERE o.player_id = p.id AND o.name = 'prayable'
                )",
            [$npcId]
        );
        if ($npc === false) {
            throw new \InvalidArgumentException('Ce PNJ est introuvable ou possède déjà un culte.');
        }

        (new PlayerOptionsService())->addOption($npcId, 'prayable');
        PlayerOptionsService::resetCache($npcId);
        BoardChanges::viewer($npcId);
        (new AuditService())->addAuditLog("CultAdminService::addCult #{$npcId} '{$npc['name']}'");
    }

    /** @param array{plan?: string, q?: string} $filters
     *  @return list<array{id:int,name:string,player_type:string,x:int,y:int,z:int,plan:string}>
     */
    public function listGods(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'player_type' => (string) $row['player_type'],
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'z' => (int) $row['z'],
            'plan' => (string) $row['plan'],
        ], $this->connection->fetchAllAssociative(
            "SELECT p.id, p.name, p.player_type, c.x, c.y, c.z, c.plan
               FROM players p
               JOIN coords c ON c.id = p.coords_id
               {$where}
              ORDER BY p.name, p.id
              LIMIT {$limit} OFFSET {$offset}",
            $params
        ));
    }

    /** @param array{plan?: string, q?: string} $filters */
    public function countGods(array $filters = []): int
    {
        [$where, $params] = $this->filterSql($filters);

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM players p JOIN coords c ON c.id = p.coords_id {$where}",
            $params
        );
    }

    /** @return array{id:int,name:string,player_type:string,x:int,y:int,z:int,plan:string}|null */
    public function getGod(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT p.id, p.name, p.player_type, c.x, c.y, c.z, c.plan
               FROM players p
               JOIN coords c ON c.id = p.coords_id
              WHERE p.id = ? AND {$this->prayableSql()}",
            [$id]
        );

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'player_type' => (string) $row['player_type'],
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'z' => (int) $row['z'],
            'plan' => (string) $row['plan'],
        ];
    }

    /** @return list<array{id:int,name:string,race:string,x:?int,y:?int,z:?int,plan:?string,pf:int}> */
    public function followers(int $godId): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'race' => (string) $row['race'],
            'x' => $row['x'] !== null ? (int) $row['x'] : null,
            'y' => $row['y'] !== null ? (int) $row['y'] : null,
            'z' => $row['z'] !== null ? (int) $row['z'] : null,
            'plan' => $row['plan'] !== null ? (string) $row['plan'] : null,
            'pf' => (int) $row['pf'],
        ], $this->connection->fetchAllAssociative(
            "SELECT p.id, p.name, p.race, p.pf, c.x, c.y, c.z, c.plan
               FROM players p
               JOIN accounts a ON a.player_id = p.id
               LEFT JOIN coords c ON c.id = p.coords_id
              WHERE p.godId = ? AND p.id <> ?
                AND p.player_type IN ('real', 'npc', 'tutorial')
                AND a.last_login_time >= ?
              ORDER BY p.name, p.id",
            [$godId, $godId, time() - INACTIVE_TIME]
        ));
    }

    /** @return list<array{id:int,name:string,x:int,y:int,z:int,plan:string,pv:int}> */
    public function altars(int $godId): array
    {
        $altarPv = (new RaceService())->getRaceByName('altar')?->getCarac('pv') ?? 0;
        $rows = $this->connection->fetchAllAssociative(
            "SELECT p.id, p.name, c.x, c.y, c.z, c.plan, COALESCE(pb.n, 0) AS pv_bonus
               FROM players p
               JOIN coords c ON c.id = p.coords_id
               LEFT JOIN players_bonus pb ON pb.player_id = p.id AND pb.name = 'pv'
              WHERE p.player_type = 'building' AND p.race = 'altar' AND p.godId = ?
              ORDER BY c.plan, p.name, p.id",
            [$godId]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'x' => (int) $row['x'],
            'y' => (int) $row['y'],
            'z' => (int) $row['z'],
            'plan' => (string) $row['plan'],
            'pv' => $altarPv + (int) $row['pv_bonus'],
        ], $rows);
    }

    public function create(string $name, int $x, int $y, int $z, string $plan): int
    {
        $name = $this->validateName($name);
        $coordsId = $this->coordsId($x, $y, $z, $plan);

        $id = (new NpcAdminService())->createPnj($name, 'dieu');
        $oldCoordsId = (int) $this->connection->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$id]);
        $this->connection->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $id]);
        (new PlayerOptionsService())->addOption($id, 'prayable');
        (new EntityCellService())->syncCells($id);
        BoardChanges::cellId($oldCoordsId, $coordsId);
        BoardChanges::viewer($id);

        (new AuditService())->addAuditLog("CultAdminService::create #{$id} '{$name}'");

        return $id;
    }

    public function update(int $id, string $name, int $x, int $y, int $z, string $plan): void
    {
        $god = $this->getGod($id);
        if ($god === null) {
            throw new \InvalidArgumentException("Aucun dieu #{$id}.");
        }

        $name = $this->validateName($name);
        $coordsId = $this->coordsId($x, $y, $z, $plan);
        $oldCoordsId = (int) $this->connection->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$id]);
        $this->connection->executeStatement(
            "UPDATE players SET name = ?, coords_id = ? WHERE id = ? AND race = 'dieu'",
            [$name, $coordsId, $id]
        );
        (new EntityCellService())->syncCells($id);
        BoardChanges::cellId($oldCoordsId, $coordsId);
        BoardChanges::viewer($id);

        (new AuditService())->addAuditLog("CultAdminService::update #{$id} '{$name}'");
    }

    /** @return array{followers:int,altars:int} Remove the cult, preserving any underlying player account. */
    public function remove(int $id): array
    {
        $god = $this->getGod($id);
        if ($god === null) {
            throw new \InvalidArgumentException("Aucun dieu #{$id}.");
        }

        $memberIds = array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT id FROM players WHERE godId = ? AND player_type IN (\'real\', \'npc\', \'tutorial\')',
            [$id]
        ));
        $altarRows = $this->connection->fetchAllAssociative(
            "SELECT id, coords_id FROM players WHERE player_type = 'building' AND race = 'altar' AND godId = ?",
            [$id]
        );
        $altarCoords = array_map(static fn (array $row): int => (int) $row['coords_id'], $altarRows);

        $this->connection->transactional(function (Connection $connection) use ($id): void {
            $connection->executeStatement(
                "UPDATE players SET godId = 0, pf = 0
                  WHERE godId = ? AND player_type IN ('real', 'npc', 'tutorial')",
                [$id]
            );
            $connection->executeStatement(
                "UPDATE players SET godId = 0, name = 'Autel'
                  WHERE player_type = 'building' AND race = 'altar' AND godId = ?",
                [$id]
            );
            $connection->executeStatement(
                "DELETE FROM players_options WHERE player_id = ? AND name = 'prayable'",
                [$id]
            );
        });

        PlayerOptionsService::resetCache($id);
        BoardChanges::viewer($id);
        if ($altarCoords !== []) {
            BoardChanges::cellId(...$altarCoords);
        }
        foreach ($memberIds as $memberId) {
            BoardChanges::viewer($memberId);
        }

        if ($god['player_type'] === 'npc') {
            (new NpcAdminService())->softRetire($id);
        }
        Player::refresh_classements();

        (new AuditService())->addAuditLog(
            'CultAdminService::remove #' . $id . ' (' . count($memberIds) . ' fidèles, ' . count($altarRows) . ' autels)'
        );

        return ['followers' => count($memberIds), 'altars' => count($altarRows)];
    }

    /** @param array{plan?: string, q?: string} $filters
     *  @return array{0:string,1:list<int|string>}
     */
    private function filterSql(array $filters): array
    {
        $clauses = [$this->prayableSql()];
        $params = [];
        if (($filters['plan'] ?? '') !== '') {
            $clauses[] = 'c.plan = ?';
            $params[] = $filters['plan'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            if (preg_match('/^-?\d+$/', $filters['q'])) {
                $clauses[] = '(p.name LIKE ? OR p.id = ?)';
                array_push($params, $like, (int) $filters['q']);
            } else {
                $clauses[] = 'p.name LIKE ?';
                $params[] = $like;
            }
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function prayableSql(): string
    {
        return "EXISTS (SELECT 1 FROM players_options o WHERE o.player_id = p.id AND o.name = 'prayable')";
    }

    private function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('Le nom du dieu doit contenir entre 1 et 255 caractères.');
        }

        return $name;
    }

    private function coordsId(int $x, int $y, int $z, string $plan): int
    {
        if (!in_array($plan, $this->plans(), true)) {
            throw new \InvalidArgumentException('Plan inconnu.');
        }

        $coordsId = View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => $plan]);
        if ($coordsId === null) {
            throw new \InvalidArgumentException('Impossible de créer la position choisie.');
        }

        return (int) $coordsId;
    }
}
