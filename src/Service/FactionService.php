<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Entity\Faction;
use App\Entity\FactionRole;
use RuntimeException;

/**
 * Single gateway to faction definitions, now stored in the DB (factions,
 * faction_roles) instead of datas/*\/factions/*.json.
 *
 * getFactionData() returns the exact shape the JSON files had so the
 * historical call sites keep working unchanged — including the load-bearing
 * detail that `hidden` and `secret` are OMITTED when false (callers test
 * them with !empty()/isset(), e.g. scripts/faction/body.php).
 */
class FactionService
{
    /** @var array<string, Faction|null> Per-request cache, keyed by lowercase code. */
    private static array $cache = [];

    private $entityManager;

    public function __construct()
    {
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Returns the Faction matching the given code (the string stored in
     * players.faction / players.secretFaction), or null if not found.
     */
    public function getFactionByCode(string $code): ?Faction
    {
        $code = strtolower($code);
        if ($code === '') {
            return null;
        }

        if (!array_key_exists($code, self::$cache)) {
            self::$cache[$code] = $this->entityManager
                ->getRepository(Faction::class)
                ->findOneBy(['code' => $code]);
        }

        return self::$cache[$code];
    }

    /**
     * Full faction data in the shape the faction JSON files used to have
     * (->name, ->text, ->raFont, ->respawnPlan, ->role[] with per-role
     * permission flags; ->hidden/->secret only when true). Returns null for
     * unknown or empty codes — the same falsy result json()->decode gave on
     * a missing file, preserving every `if(!$facJson)` guard.
     */
    public function getFactionData(string $code): ?object
    {
        $faction = $this->getFactionByCode($code);
        if ($faction === null) {
            return null;
        }

        $data = (object) [
            'name' => $faction->getName(),
            'text' => $faction->getText(),
            'raFont' => $faction->getRaFont(),
            'respawnPlan' => $faction->getRespawnPlan(),
            'role' => array_map(
                static fn (FactionRole $role): object => $role->toJsonObject(),
                $faction->getRoles()->getValues()
            ),
        ];
        if ($faction->isHidden()) {
            $data->hidden = 1;
        }
        if ($faction->isSecret()) {
            $data->secret = 1;
        }

        return $data;
    }

    /**
     * Every faction, for admin lists and select boxes.
     *
     * @return Faction[]
     */
    public function getAllFactions(): array
    {
        return $this->entityManager->getRepository(Faction::class)
            ->findBy([], ['code' => 'ASC']);
    }

    /**
     * @return string[] All faction codes.
     */
    public function getAllFactionCodes(): array
    {
        return array_map(static fn (Faction $faction): string => $faction->getCode(), $this->getAllFactions());
    }

    /**
     * @return array<string, string> code => display name, for select lists.
     */
    public function getFactionNames(): array
    {
        $names = [];
        foreach ($this->getAllFactions() as $faction) {
            $names[$faction->getCode()] = $faction->getName();
        }

        return $names;
    }

    /**
     * Position of the role flagged defaultRole (the one members get when
     * nothing better is known), first role as fallback.
     */
    public function getDefaultRolePosition(Faction $faction): int
    {
        foreach ($faction->getRoles() as $role) {
            if ($role->getFlag('defaultRole')) {
                return $role->getPosition();
            }
        }

        return 0;
    }

    /**
     * Persist a (new or edited) faction and drop the request cache.
     */
    public function save(Faction $faction): void
    {
        $this->entityManager->persist($faction);
        $this->entityManager->flush();
        self::clearCache();
    }

    /**
     * Replace a faction's role list (admin edit). Plain SQL delete+insert:
     * positions are reindexed 0..n-1 from array order, and this sidesteps ORM
     * insert-before-delete collisions on the (faction_id, position) unique
     * key. Careful: reordering shifts what players.factionRole points at.
     *
     * @param array<int, array{name?: string, flags?: array<string, bool>}> $roles
     */
    public function replaceRoles(Faction $faction, array $roles): void
    {
        $connection = $this->entityManager->getConnection();

        $connection->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$faction->getId()]);

        $position = 0;
        foreach ($roles as $role) {
            $name = trim((string) ($role['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $params = [$faction->getId(), $position++, $name];
            foreach (FactionRole::FLAG_KEYS as $key) {
                $params[] = (int) !empty($role['flags'][$key]);
            }

            $connection->executeStatement(
                'INSERT INTO faction_roles (faction_id, position, name, '
                    . implode(', ', FactionRole::FLAG_KEYS) . ')
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $params
            );
        }

        // The identity-mapped entity still holds the pre-edit collection;
        // refresh so same-request readers see the new roles.
        $this->entityManager->refresh($faction);
        self::clearCache();
    }

    /**
     * Nombre de personnages référençant cette faction, en membre principal
     * (players.faction) et en membre secret (players.secretFaction) — le
     * garde-fou de la suppression.
     *
     * @return array{members: int, secretMembers: int}
     */
    public function countPlayersUsingFaction(string $code): array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            // Les structures portent une faction (satellite des bâtiments)
            // mais ne sont pas des MEMBRES : personnages seulement.
            'SELECT COALESCE(SUM(faction = ?), 0) AS members,
                    COALESCE(SUM(secretFaction = ?), 0) AS secretMembers
             FROM players WHERE player_type IN ("real", "npc")',
            [$code, $code]
        );

        return [
            'members' => (int) ($row['members'] ?? 0),
            'secretMembers' => (int) ($row['secretMembers'] ?? 0),
        ];
    }

    /**
     * Membres par faction en deux requêtes groupées (la liste admin
     * l'affiche pour toutes les factions sans une requête par ligne).
     *
     * @return array<string, array{members: int, secretMembers: int}>
     */
    public function countMembersByFaction(): array
    {
        $connection = $this->entityManager->getConnection();
        $counts = [];

        $sql = [
            'members' => "SELECT faction AS code, COUNT(*) AS n FROM players WHERE faction <> '' AND player_type IN ('real', 'npc') GROUP BY faction",
            'secretMembers' => "SELECT secretFaction AS code, COUNT(*) AS n FROM players WHERE secretFaction <> '' AND player_type IN ('real', 'npc') GROUP BY secretFaction",
        ];
        foreach ($sql as $key => $query) {
            foreach ($connection->fetchAllAssociative($query) as $row) {
                $code = (string) $row['code'];
                $counts[$code] ??= ['members' => 0, 'secretMembers' => 0];
                $counts[$code][$key] = (int) $row['n'];
            }
        }

        return $counts;
    }

    /**
     * The faction's buildings standing on the board — its assets, for the
     * faction panel. Carrying the faction is enough (a building is never a
     * MEMBER, the counts above say so on purpose); what is shelved or
     * vanished stands nowhere and is not listed.
     *
     * @return array<int, array{id: int, name: string, type: string, label: string,
     *                          playable: bool, build_state: string, site_done: ?int,
     *                          site_total: ?int, x: int, y: int, plan: string}>
     */
    public function buildingsOf(string $code): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT p.id, p.name, p.race, r.label, r.playable,
                    b.build_state, cs.work_done AS site_done, cs.work_total AS site_total,
                    c.x, c.y, c.plan
               FROM players p
               JOIN buildings b ON b.player_id = p.id
               JOIN coords c ON c.id = p.coords_id
               LEFT JOIN races r ON CONVERT(r.name USING utf8mb4) = CONVERT(p.race USING utf8mb4)
               LEFT JOIN construction_sites cs ON cs.player_id = p.id
              WHERE p.player_type = 'building'
                AND CONVERT(p.faction USING utf8mb4) = CONVERT(? USING utf8mb4)
              ORDER BY c.plan, p.name",
            [strtolower(trim($code))]
        );

        return array_map(static fn (array $row): array => [
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'type'        => (string) $row['race'],
            'label'       => (string) ($row['label'] ?? '') !== '' ? (string) $row['label'] : ucfirst((string) $row['race']),
            'playable'    => (bool) ($row['playable'] ?? false),
            'build_state' => (string) $row['build_state'],
            'site_done'   => $row['site_done'] !== null ? (int) $row['site_done'] : null,
            'site_total'  => $row['site_total'] !== null ? (int) $row['site_total'] : null,
            'x'           => (int) $row['x'],
            'y'           => (int) $row['y'],
            'plan'        => (string) $row['plan'],
        ], $rows);
    }

    /**
     * The role row a member holds — players.faction + players.factionRole
     * (a POSITION in the faction's ordered role list). Null when factionless
     * or when the position points at no row (a reordered list can orphan it).
     *
     * @return array<string, mixed>|null
     */
    public function roleOf(int $playerId): ?array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT fr.* FROM players p
               JOIN factions f ON CONVERT(f.code USING utf8mb4) = CONVERT(p.faction USING utf8mb4)
               JOIN faction_roles fr ON fr.faction_id = f.id AND fr.position = p.factionRole
              WHERE p.id = ?',
            [$playerId]
        );

        return $row ?: null;
    }

    /**
     * May this member perform a management gesture? The flag lives on the
     * ROLE (faction_roles) — the game finally reads what the admin edits.
     */
    public function mayManage(int $actorId, string $flag): bool
    {
        if (!in_array($flag, FactionRole::FLAG_KEYS, true)) {
            return false;
        }

        $role = $this->roleOf($actorId);

        return $role !== null && !empty($role[$flag]);
    }

    /**
     * Enrolls a factionless character into the actor's faction, at the
     * default role. Refusals throw, with the words the player reads.
     */
    public function addMember(int $actorId, string $targetName): string
    {
        if (!$this->mayManage($actorId, 'addMember')) {
            throw new RuntimeException('Votre rang ne permet pas de recruter.');
        }

        $conn = $this->entityManager->getConnection();
        $factionCode = (string) $conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);

        $target = $conn->fetchAssociative(
            "SELECT id, name, faction FROM players
              WHERE CONVERT(name USING utf8mb4) = CONVERT(? USING utf8mb4) AND player_type = 'real'",
            [trim($targetName)]
        );
        if ($target === false) {
            throw new RuntimeException('Personne de ce nom.');
        }
        if ((string) $target['faction'] !== '') {
            throw new RuntimeException($target['name'] . ' appartient déjà à une faction.');
        }

        $faction = $this->getFactionByCode($factionCode);
        $conn->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [$factionCode, $faction !== null ? $this->getDefaultRolePosition($faction) : 0, (int) $target['id']]
        );
        (new AuditService())->addAuditLog("faction {$factionCode}: #{$actorId} recrute #{$target['id']}");

        return (string) $target['name'];
    }

    /**
     * Removes a member of the actor's faction. One does not banish oneself,
     * nor anyone at one's rank or above: the LADDER is the hierarchy
     * (position ascends — Forgeron 0, Roi at the top), and every gesture
     * reaches strictly below.
     */
    public function kickMember(int $actorId, int $targetId): void
    {
        $actorRole = $this->roleOf($actorId);
        if ($actorRole === null || empty($actorRole['kickMember'])) {
            throw new RuntimeException('Votre rang ne permet pas de renvoyer.');
        }
        if ($targetId === $actorId) {
            throw new RuntimeException('On ne se bannit pas soi-même.');
        }

        $conn = $this->entityManager->getConnection();
        $factionCode = (string) $conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);
        $target = $conn->fetchAssociative('SELECT faction, factionRole FROM players WHERE id = ?', [$targetId]);

        if ($target === false || (string) $target['faction'] !== $factionCode) {
            throw new RuntimeException('Cette personne n\'est pas des vôtres.');
        }
        if ((int) $target['factionRole'] >= (int) $actorRole['position']) {
            throw new RuntimeException('Cette personne vous dépasse.');
        }

        $conn->executeStatement(
            "UPDATE players SET faction = '', factionRole = 0 WHERE id = ?",
            [$targetId]
        );
        (new AuditService())->addAuditLog("faction {$factionCode}: #{$actorId} renvoie #{$targetId}");
    }

    /**
     * Moves a member of the actor's faction to another of its roles —
     * a member strictly below, toward a rank strictly below: nobody
     * raises a peer, nobody raises anyone to their own rank.
     */
    public function assignRole(int $actorId, int $targetId, int $position): void
    {
        $actorRole = $this->roleOf($actorId);
        if ($actorRole === null || empty($actorRole['editRole'])) {
            throw new RuntimeException('Votre rang ne permet pas de changer les rangs.');
        }

        $conn = $this->entityManager->getConnection();
        $factionCode = (string) $conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);
        $target = $conn->fetchAssociative('SELECT faction, factionRole FROM players WHERE id = ?', [$targetId]);

        if ($target === false || (string) $target['faction'] !== $factionCode) {
            throw new RuntimeException('Cette personne n\'est pas des vôtres.');
        }

        $known = $conn->fetchOne(
            'SELECT fr.id FROM faction_roles fr
               JOIN factions f ON f.id = fr.faction_id
              WHERE CONVERT(f.code USING utf8mb4) = CONVERT(? USING utf8mb4) AND fr.position = ?',
            [$factionCode, $position]
        );
        if ($known === false) {
            throw new RuntimeException('Ce rang n\'existe pas.');
        }

        $actorPosition = (int) $actorRole['position'];
        if ((int) $target['factionRole'] >= $actorPosition) {
            throw new RuntimeException('Cette personne vous dépasse.');
        }
        if ($position >= $actorPosition) {
            throw new RuntimeException('On n\'élève personne à son propre rang.');
        }

        $conn->executeStatement('UPDATE players SET factionRole = ? WHERE id = ?', [$position, $targetId]);
        (new AuditService())->addAuditLog("faction {$factionCode}: #{$actorId} donne le rang {$position} à #{$targetId}");
    }

    /** The capability flags a ladder-holder may grant — the landing rank
     *  (defaultRole) is the ladder's structure, not a capability. */
    public const GRANTABLE_FLAGS = ['showPosition', 'showForum', 'addMember', 'editRole', 'kickMember', 'initRole'];

    /**
     * Renames a rank and sets what it authorizes — the ladder-holder's
     * gesture (initRole), on ranks strictly below their own: nobody
     * rewrites their own charter, nor a superior's.
     *
     * @param array<string, mixed> $flags flag => truthy, GRANTABLE_FLAGS only
     * @param string $nameAlt the rank's second name (Roi / Reine), '' = single
     */
    public function updateRoleDefinition(int $actorId, int $position, string $name, array $flags, string $nameAlt = ''): void
    {
        $actorRole = $this->roleOf($actorId);
        if ($actorRole === null || empty($actorRole['initRole'])) {
            throw new RuntimeException('Votre rang ne permet pas de régler l\'échelle.');
        }
        if ($position >= (int) $actorRole['position']) {
            throw new RuntimeException('Ce rang vous dépasse.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Un rang porte un nom.');
        }

        $conn = $this->entityManager->getConnection();
        $factionCode = (string) $conn->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);

        $sets = ['fr.name = ?', 'fr.name_alt = ?'];
        $params = [$name, trim($nameAlt)];
        foreach (self::GRANTABLE_FLAGS as $flag) {
            $sets[] = "fr.{$flag} = ?";
            $params[] = empty($flags[$flag]) ? 0 : 1;
        }
        $params[] = $factionCode;
        $params[] = $position;

        $affected = $conn->executeStatement(
            'UPDATE faction_roles fr
               JOIN factions f ON f.id = fr.faction_id
                SET ' . implode(', ', $sets) . '
              WHERE CONVERT(f.code USING utf8mb4) = CONVERT(? USING utf8mb4) AND fr.position = ?',
            $params
        );
        if ($affected === 0) {
            throw new RuntimeException('Ce rang n\'existe pas.');
        }

        self::clearCache();
        (new AuditService())->addAuditLog("faction {$factionCode}: #{$actorId} règle le rang {$position} « {$name} »");
    }

    /**
     * The ladder itself, position-ascending — full rows, flags included,
     * for the panel's ladder editor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rolesOf(string $code): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT fr.* FROM faction_roles fr
               JOIN factions f ON f.id = fr.faction_id
              WHERE CONVERT(f.code USING utf8mb4) = CONVERT(? USING utf8mb4)
              ORDER BY fr.position ASC',
            [strtolower(trim($code))]
        );
    }

    /** The summit of a faction's ladder. */
    public function topPositionOf(string $code): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(fr.position), 0) FROM faction_roles fr
               JOIN factions f ON f.id = fr.faction_id
              WHERE CONVERT(f.code USING utf8mb4) = CONVERT(? USING utf8mb4)',
            [strtolower(trim($code))]
        );
    }

    /**
     * The STRUCTURE of the ladder belongs to the main role alone — the
     * summit. Lower initRole holders settle charters, never the frame.
     *
     * @return array{role: array<string, mixed>, code: string}
     */
    private function requireTop(int $actorId): array
    {
        $role = $this->roleOf($actorId);
        $code = (string) $this->entityManager->getConnection()
            ->fetchOne('SELECT faction FROM players WHERE id = ?', [$actorId]);

        if ($role === null || (int) $role['position'] !== $this->topPositionOf($code)) {
            throw new RuntimeException('Seul le plus haut rang règle la structure de l\'échelle.');
        }

        return ['role' => $role, 'code' => $code];
    }

    /** The landing rank: where a newcomer arrives. One rung, below the summit. */
    public function setLandingRank(int $actorId, int $position): void
    {
        ['role' => $role, 'code' => $code] = $this->requireTop($actorId);

        if ($position >= (int) $role['position']) {
            throw new RuntimeException('Le sommet n\'accueille pas les nouveaux venus.');
        }

        $conn = $this->entityManager->getConnection();
        $factionId = (int) $conn->fetchOne('SELECT id FROM factions WHERE code = ?', [$code]);

        $known = $conn->fetchOne(
            'SELECT id FROM faction_roles WHERE faction_id = ? AND position = ?',
            [$factionId, $position]
        );
        if ($known === false) {
            throw new RuntimeException('Ce rang n\'existe pas.');
        }

        $conn->transactional(static function ($conn) use ($factionId, $position): void {
            $conn->executeStatement('UPDATE faction_roles SET defaultRole = 0 WHERE faction_id = ?', [$factionId]);
            $conn->executeStatement(
                'UPDATE faction_roles SET defaultRole = 1 WHERE faction_id = ? AND position = ?',
                [$factionId, $position]
            );
        });

        self::clearCache();
        (new AuditService())->addAuditLog("faction {$code}: #{$actorId} fait du rang {$position} le rang d'accueil");
    }

    /** A new rung enters just below the summit; reorder places it after. */
    public function addRank(int $actorId, string $name): void
    {
        ['role' => $role, 'code' => $code] = $this->requireTop($actorId);

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Un rang porte un nom.');
        }

        $conn = $this->entityManager->getConnection();
        $factionId = (int) $conn->fetchOne('SELECT id FROM factions WHERE code = ?', [$code]);
        $top = (int) $role['position'];

        $conn->transactional(static function ($conn) use ($factionId, $top, $name): void {
            // The summit slides up one, its holders with it.
            $conn->executeStatement(
                'UPDATE faction_roles SET position = position + 1 WHERE faction_id = ? AND position = ?',
                [$factionId, $top]
            );
            $conn->executeStatement(
                'UPDATE players p JOIN factions f ON CONVERT(f.code USING utf8mb4) = CONVERT(p.faction USING utf8mb4)
                    SET p.factionRole = p.factionRole + 1
                  WHERE f.id = ? AND p.factionRole = ?',
                [$factionId, $top]
            );
            $conn->executeStatement(
                'INSERT INTO faction_roles (faction_id, position, name) VALUES (?, ?, ?)',
                [$factionId, $top, $name]
            );
        });

        self::clearCache();
        (new AuditService())->addAuditLog("faction {$code}: #{$actorId} ajoute le rang « {$name} »");
    }

    /** A rung leaves only empty, and never the landing one; the gap closes. */
    public function removeRank(int $actorId, int $position): void
    {
        ['role' => $role, 'code' => $code] = $this->requireTop($actorId);

        if ($position >= (int) $role['position']) {
            throw new RuntimeException('Ce rang vous dépasse.');
        }

        $conn = $this->entityManager->getConnection();
        $factionId = (int) $conn->fetchOne('SELECT id FROM factions WHERE code = ?', [$code]);

        $rung = $conn->fetchAssociative(
            'SELECT id, defaultRole FROM faction_roles WHERE faction_id = ? AND position = ?',
            [$factionId, $position]
        );
        if ($rung === false) {
            throw new RuntimeException('Ce rang n\'existe pas.');
        }
        if (!empty($rung['defaultRole'])) {
            throw new RuntimeException('C\'est le rang d\'accueil — désignez-en un autre d\'abord.');
        }

        $held = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM players p JOIN factions f ON CONVERT(f.code USING utf8mb4) = CONVERT(p.faction USING utf8mb4)
              WHERE f.id = ? AND p.factionRole = ?',
            [$factionId, $position]
        );
        if ($held > 0) {
            throw new RuntimeException('Des membres tiennent ce rang.');
        }

        $conn->transactional(static function ($conn) use ($factionId, $position): void {
            $conn->executeStatement(
                'DELETE FROM faction_roles WHERE faction_id = ? AND position = ?',
                [$factionId, $position]
            );
            $conn->executeStatement(
                'UPDATE faction_roles SET position = position - 1 WHERE faction_id = ? AND position > ?',
                [$factionId, $position]
            );
            $conn->executeStatement(
                'UPDATE players p JOIN factions f ON CONVERT(f.code USING utf8mb4) = CONVERT(p.faction USING utf8mb4)
                    SET p.factionRole = p.factionRole - 1
                  WHERE f.id = ? AND p.factionRole > ?',
                [$factionId, $position]
            );
        });

        self::clearCache();
        (new AuditService())->addAuditLog("faction {$code}: #{$actorId} retire le rang {$position}");
    }

    /**
     * Swaps a rung with its neighbor, members riding their rung — the
     * summit never moves.
     */
    public function moveRank(int $actorId, int $position, int $direction): void
    {
        ['role' => $role, 'code' => $code] = $this->requireTop($actorId);

        $other = $position + ($direction >= 0 ? 1 : -1);
        $top = (int) $role['position'];

        if ($position >= $top || $other >= $top || $other < 0) {
            throw new RuntimeException('Ce mouvement sort de l\'échelle.');
        }

        $conn = $this->entityManager->getConnection();
        $factionId = (int) $conn->fetchOne('SELECT id FROM factions WHERE code = ?', [$code]);

        $both = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM faction_roles WHERE faction_id = ? AND position IN (?, ?)',
            [$factionId, $position, $other]
        );
        if ($both !== 2) {
            throw new RuntimeException('Ce rang n\'existe pas.');
        }

        $conn->transactional(static function ($conn) use ($factionId, $position, $other): void {
            // Through -1: the swap never collides with itself.
            $conn->executeStatement('UPDATE faction_roles SET position = -1 WHERE faction_id = ? AND position = ?', [$factionId, $position]);
            $conn->executeStatement('UPDATE faction_roles SET position = ? WHERE faction_id = ? AND position = ?', [$position, $factionId, $other]);
            $conn->executeStatement('UPDATE faction_roles SET position = ? WHERE faction_id = ? AND position = -1', [$other, $factionId]);

            $conn->executeStatement(
                'UPDATE players p JOIN factions f ON CONVERT(f.code USING utf8mb4) = CONVERT(p.faction USING utf8mb4)
                    SET p.factionRole = CASE p.factionRole WHEN ? THEN ? ELSE ? END
                  WHERE f.id = ? AND p.factionRole IN (?, ?)',
                [$position, $other, $position, $factionId, $position, $other]
            );
        });

        self::clearCache();
        (new AuditService())->addAuditLog("faction {$code}: #{$actorId} échange les rangs {$position} et {$other}");
    }

    /**
     * Supprime une faction qu'aucun personnage ne référence. players.faction
     * et players.secretFaction étant des colonnes texte sans contrainte, une
     * suppression avec des membres restants les laisserait avec une faction
     * fantôme (page en erreur, respawn par défaut) — refusée. Les rôles
     * partent en cascade.
     *
     * @throws RuntimeException quand des personnages utilisent encore la faction
     */
    public function deleteFaction(Faction $faction): void
    {
        $counts = $this->countPlayersUsingFaction($faction->getCode());
        $total = $counts['members'] + $counts['secretMembers'];
        if ($total > 0) {
            throw new RuntimeException(sprintf(
                'La faction « %s » est encore utilisée par %d personnage(s) — réaffectez-les (page Membres) au lieu de la supprimer.',
                $faction->getCode(),
                $total
            ));
        }

        $this->entityManager->remove($faction);
        $this->entityManager->flush();
        self::clearCache();
    }

    /**
     * Drop the per-request cache (after admin edits or in tests).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
