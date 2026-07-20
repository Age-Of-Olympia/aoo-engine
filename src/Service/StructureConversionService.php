<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use RuntimeException;

/**
 * Migre à la demande un objet de l'ANCIEN système de construction
 * (items type « structure », posés par feu build.php) vers le système
 * actuel : pseudo-race structure + type constructible — l'action
 * générique `construire` fait le reste (ItemPick, POST itemId).
 *
 * Même logique que la migration Version20260719190000 pour les 33
 * objets du catalogue historique — ce service couvre les retardataires
 * découverts en environnement (objets hors catalogue), depuis le
 * bouton « Migrer » de la liste admin des objets. Les caractéristiques
 * de la race créée (PV, couleurs, blocage passage/tirs) partent sur
 * des défauts prudents : à affiner ensuite dans l'admin des races.
 */
class StructureConversionService
{
    private const DEFAULT_PV = 10;
    private const DEFAULT_BG_COLOR = '#8a8a8a';

    /**
     * @return string récapitulatif humain de ce qui a été fait
     *
     * @throws RuntimeException objet introuvable ou déjà migré
     */
    public function convertItem(string $name): string
    {
        $conn = EntityManagerFactory::getEntityManager()->getConnection();
        $webroot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
        $done = [];

        $item = $conn->fetchAssociative(
            'SELECT id, private, stats_in_db, type, text, extra FROM items WHERE name = ?',
            [$name]
        );
        if ($item === false) {
            throw new RuntimeException("Objet « {$name} » introuvable.");
        }

        // Étiquette d'affichage : le JSON historique la porte ; sinon extra,
        // sinon le code embelli.
        $label = null;
        $json = $this->historicalJson($webroot, $name, (int) $item['private']);
        if (is_array($json) && !empty($json['name'])) {
            $label = (string) $json['name'];
        }
        if ($label === null && !empty($item['extra'])) {
            $extraExisting = json_decode((string) $item['extra'], true);
            if (is_array($extraExisting) && !empty($extraExisting['name'])) {
                $label = (string) $extraExisting['name'];
            }
        }
        $label ??= ucfirst(str_replace('_', ' ', $name));

        // --- 1. stats en base si le JSON legacy est encore la source -------
        if (!(int) $item['stats_in_db'] && is_array($json)) {
            $this->seedFromJson($conn, (int) $item['id'], $json);
            $done[] = 'stats importées du JSON';
        }

        // --- 2. pseudo-race structure --------------------------------------
        $pv = (int) (defined('RESOURCES_PV') ? (RESOURCES_PV[$name] ?? 0) : 0);
        $created = $conn->executeStatement(
            "INSERT IGNORE INTO races
                (code, name, label, description, playable, hidden, kind, structure_nature,
                 bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
             VALUES (?, ?, ?, '', 0, 1, 'structure', 'obstacle', '', '#cd7f32', 1, 1, ?, 'black', '', '', ?)",
            [strtoupper($name), $name, $label, self::DEFAULT_BG_COLOR, $pv > 0 ? $pv : self::DEFAULT_PV]
        );
        $done[] = $created ? "race « {$label} » créée (PV, couleurs et blocages à affiner dans l'admin des races)" : 'race déjà en place';
        RaceService::clearCache();

        // --- 3. l'objet devient constructible ------------------------------
        $extra = ['name' => $label];
        if (!is_file($webroot . '/img/items/' . $name . '.webp') && is_file($webroot . '/img/walls/' . $name . '.png')) {
            $extra['img'] = 'img/walls/' . $name . '.png';
            $extra['mini'] = 'img/walls/' . $name . '.png';
        }
        $row = $conn->fetchAssociative('SELECT extra, text FROM items WHERE id = ?', [(int) $item['id']]);
        if (!empty($row['extra'])) {
            $existing = json_decode((string) $row['extra'], true);
            if (is_array($existing)) {
                $extra = array_merge($extra, $existing);
                $extra['name'] = $label;
            }
        }
        $conn->executeStatement(
            "UPDATE items SET type = 'constructible', subtype = '', stats_in_db = 1, extra = ? WHERE id = ?",
            [json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $item['id']]
        );
        $done[] = 'type constructible';

        // --- 4. l'action de construction -----------------------------------
        // Plus d'action par type : l'action générique `construire` reçoit
        // l'objet à l'exécution (ItemPick) — être `constructible` suffit.
        $done[] = 'action générique construire (rien à créer)';

        // --- 5. le seed JSON suit la base ----------------------------------
        // La base est désormais la source ; le JSON historique reste comme
        // seed et le golden master vérifie qu'ils concordent — la divergence
        // voulue (type constructible) doit donc y couler aussi.
        if (is_array($json)) {
            $json['type'] = 'constructible';
            unset($json['subtype']);
            $dir = ((int) $item['private']) ? 'private' : 'public';
            $path = $webroot . '/datas/' . $dir . '/items/' . $name . '.json';
            if (is_writable($path)) {
                file_put_contents(
                    $path,
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
                );
                $done[] = 'seed JSON aligné';
            }
        }

        return ucfirst($name) . ' : ' . implode(', ', $done) . '.';
    }

    /** @return array<string, mixed>|null */
    private function historicalJson(string $webroot, string $name, int $private): ?array
    {
        $dir = $private ? 'private' : 'public';
        $path = $webroot . '/datas/' . $dir . '/items/' . $name . '.json';
        if (!is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : null;
    }

    /**
     * Import sans perte du JSON historique vers les colonnes items —
     * mêmes règles que ItemStatsSeeder (le surplus voyage dans extra).
     *
     * @param array<string, mixed> $json
     */
    private function seedFromJson(\Doctrine\DBAL\Connection $conn, int $itemId, array $json): void
    {
        $set = ['stats_in_db = 1'];
        $params = [];
        $extra = [];

        foreach ($json as $key => $value) {
            if (in_array($key, ItemStatsSeeder::SKIP_KEYS, true)) {
                continue;
            }
            if (in_array($key, ItemStatsSeeder::SCALAR_KEYS, true)) {
                $set[] = "`{$key}` = ?";
                $params[] = is_numeric($value) ? $value : (string) $value;
            } elseif (in_array($key, ItemStatsSeeder::JSON_KEYS, true)) {
                $column = $key === 'addEffects' ? 'add_effects' : $key;
                $set[] = "`{$column}` = ?";
                $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $extra[$key] = $value;
            }
        }

        if ($extra !== []) {
            $set[] = '`extra` = ?';
            $params[] = json_encode($extra, JSON_UNESCAPED_UNICODE);
        }

        $params[] = $itemId;
        $conn->executeStatement('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
    }

}
