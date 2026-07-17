<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Items JSON→DB (même mouvement que les races) : recopie les stats de
 * datas/[public|private]/items/{name}.json dans les colonnes de la
 * table items, SANS PERTE — toute clé non colonnisée part dans le
 * fourre-tout `extra` (JSON) et est reservie verbatim par la
 * passerelle de Item::get_data().
 *
 * Partagé par la migration Version20260717180000_ItemsFromJson et la
 * page admin/item-seed.php (en prod le déploiement tourne sans datas/,
 * le seed se rejoue depuis la racine web — pattern races).
 *
 * Ne touche JAMAIS : les flags déjà en base (cursed, enchanted, spell…
 * les copies JSON sont des instantanés périmés), ni les lignes déjà
 * marquées stats_in_db (réglages admin préservés).
 */
class ItemStatsSeeder
{
    /** Colonnes scalaires seedées 1:1 depuis la clé JSON homonyme. */
    public const SCALAR_KEYS = [
        'text', 'price', 'emplacement', 'type', 'subtype', 'race',
        'esquive', 'pr', 'pf', 'malus', 'spellMalus', 'fixedF', 'mDamage',
        'demolition', 'craftedByN', 'lootChance',
        'a', 'mvt', 'p', 'pv', 'cc', 'ct', 'f', 'e', 'agi', 'pm',
        'fm', 'm', 'r', 'rm', 'spd', 'ae',
    ];

    /**
     * Sous-ensemble de SCALAR_KEYS typé chaîne (défaut '' au lieu de 0)
     * — déclaré ICI pour qu'une future colonne texte ne soit pas
     * silencieusement défaut-ée à 0 par les consommateurs (ItemImporter).
     */
    public const STRING_KEYS = ['text', 'emplacement', 'type', 'subtype', 'race'];

    /** Clés complexes stockées en colonnes JSON dédiées. */
    public const JSON_KEYS = ['munitions', 'addEffects', 'forbid'];

    /**
     * Clés JSON ignorées : identité/flags dont la base est déjà la
     * source (les copies JSON sont figées), et scories de vieilles
     * lignes jointes retrouvées dans certains fichiers.
     */
    public const SKIP_KEYS = [
        'id', 'name', 'private',
        'enchanted', 'vorpal', 'cursed', 'element', 'spell',
        'is_deprecated', 'is_bankable', 'exotique',
        'player_id', 'item_id', 'n', 'equiped',
    ];

    /** @return array{seeded: int, missing: int, kept: int} */
    public function seed(Connection $conn, string $projectRoot): array
    {
        $seeded = 0;
        $missing = 0;
        $kept = 0;

        foreach ($conn->fetchAllAssociative('SELECT id, name, private, stats_in_db FROM items') as $row) {
            if ((int) $row['stats_in_db'] === 1) {
                $kept++;
                continue;
            }

            $dir = ((int) $row['private']) ? 'private' : 'public';
            $path = $projectRoot . '/datas/' . $dir . '/items/' . $row['name'] . '.json';
            if (!is_file($path)) {
                $missing++;
                continue;
            }

            $json = json_decode((string) file_get_contents($path), true);
            if (!is_array($json)) {
                $missing++;
                continue;
            }

            $set = ['stats_in_db = 1'];
            $params = [];
            $extra = [];

            foreach ($json as $key => $value) {
                if (in_array($key, self::SKIP_KEYS, true)) {
                    continue;
                }
                if (in_array($key, self::SCALAR_KEYS, true)) {
                    $set[] = "`{$key}` = ?";
                    $params[] = is_numeric($value) ? $value : (string) $value;
                } elseif (in_array($key, self::JSON_KEYS, true)) {
                    $column = $key === 'addEffects' ? 'add_effects' : $key;
                    $set[] = "`{$column}` = ?";
                    $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                } else {
                    // Sans perte : tout le reste voyage dans extra.
                    $extra[$key] = $value;
                }
            }

            if ($extra !== []) {
                $set[] = '`extra` = ?';
                $params[] = json_encode($extra, JSON_UNESCAPED_UNICODE);
            }

            $params[] = (int) $row['id'];
            $conn->executeStatement('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
            $seeded++;
        }

        return ['seeded' => $seeded, 'missing' => $missing, 'kept' => $kept];
    }
}
