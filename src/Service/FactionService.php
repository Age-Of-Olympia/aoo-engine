<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
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
            'SELECT COALESCE(SUM(faction = ?), 0) AS members,
                    COALESCE(SUM(secretFaction = ?), 0) AS secretMembers
             FROM players',
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
            'members' => "SELECT faction AS code, COUNT(*) AS n FROM players WHERE faction <> '' GROUP BY faction",
            'secretMembers' => "SELECT secretFaction AS code, COUNT(*) AS n FROM players WHERE secretFaction <> '' GROUP BY secretFaction",
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
