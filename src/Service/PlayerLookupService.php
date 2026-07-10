<?php

namespace App\Service;

use Classes\Db;

/**
 * One place for the admin "resolve a character by matricule or exact name"
 * lookup that admin/admin-access.php and admin/pnjs-save.php each used to
 * hand-roll (with subtly different type filters). A numeric term is an exact
 * matricule (id); anything else is an exact name.
 *
 * Names are NOT unique across player_type, so resolve() returns EVERY match and
 * lets the caller decide what to do with 0 / 1 / many — avoiding the previous
 * bug where "first row wins" could target the wrong character. Real players
 * (id > 0) are ordered first for deterministic disambiguation.
 *
 * This is exact resolution; PlayerSkillsService::searchPlayers stays separate —
 * it is a fuzzy LIKE search for the picker, a different concern.
 */
class PlayerLookupService
{
    /**
     * @param list<string>|null $types Restrict to these player_type values
     *                                 (e.g. ['real'] or ['real','npc']); null = any.
     *                                 Values are code constants, never user input.
     * @return array<int, array{id:int, name:string, player_type:string}>
     */
    public function resolve(string $term, ?array $types = null): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        // Type list is always a code-supplied constant set — safe to inline,
        // which avoids mixing an int id param with string type params in bind.
        $typeClause = '';
        if ($types !== null && $types !== []) {
            $quoted = array_map(static fn(string $t): string => "'" . $t . "'", $types);
            $typeClause = ' AND player_type IN (' . implode(', ', $quoted) . ')';
        }

        if (is_numeric($term)) {
            $where = 'id = ?';
            $param = (int) $term;
        } else {
            $where = 'name = ?';
            $param = $term;
        }

        $sql = 'SELECT id, name, player_type
                FROM players
                WHERE ' . $where . $typeClause . '
                ORDER BY (id > 0) DESC, id ASC';

        $res = (new Db())->exe($sql, [$param]);

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id'          => (int) $row['id'],
                'name'        => (string) $row['name'],
                'player_type' => (string) $row['player_type'],
            ];
        }

        return $out;
    }
}
