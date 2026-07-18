<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration murs→structures : les murs constructibles de map_walls
 * deviennent de vraies entités bâtiment (PV réels, fiche, réparation),
 * le chemin de pose legacy build.php disparaît.
 *
 * 1. races.blocks_passage (une table ne bloque pas le passage, un mur
 *    si) + pseudo-races kind=structure pour chaque type de mur
 *    (pv = ex-WALLS_PV, nature obstacle, voile bronze).
 * 2. Les objets mur_pierre / table_bois / route passent constructible :
 *    actions construire_* (placestructure — placelayer pour la route,
 *    qui aménage map_routes, la couche que « courir » lit VRAIMENT :
 *    l'ancien build.php écrivait dans map_tiles, invisible pour courir).
 * 3. Chaque ligne map_walls d'un type migré devient une entité :
 *    propriétaire = player_id du poseur (NULL pour l'éditeur), PV
 *    courant = max − damages, état _broken → avatar brisé. Ressources
 *    (arbres, pierres) et décor (statues, tonneaux, coffres…) restent
 *    des map_walls, avec destroy.php pour seul public.
 *
 * Tout s'exécute via la connexion (pas addSql) : la conversion lit les
 * pseudo-races qu'elle vient d'insérer.
 */
final class Version20260719190000_WallsToStructures extends AbstractMigration
{
    /**
     * Pseudo-races des types de murs : label, pv (ex-WALLS_PV), bgColor,
     * blocks_passage / blocks_projectiles — un mur bloque les deux, une
     * table bloque le pas (on ne marche pas dessus) mais pas la flèche.
     *
     * Seuls les types CONSTRUCTIBLES par un joueur ont une pseudo-race :
     * le décor posé par l'éditeur (piliers, statues, coffres…) reste en
     * map_walls — un décor ne devient une entité structure que lorsqu'un
     * joueur le construit (placestructure).
     */
    private const WALL_RACES = [
        'mur_pierre' => ['Mur de pierre', 150, '#808080', 1, 1],
        'mur_pierre_bleue' => ['Mur de pierre bleue', 150, '#6d7fa8', 1, 1],
        'mur_noir' => ['Mur noir', 120, '#333333', 1, 1],
        'mur_bois_petrifie' => ['Mur de bois pétrifié', 120, '#7a6a58', 1, 1],
        'mur_vegetal' => ['Mur végétal', 120, '#4a7a3a', 1, 1],
        'mur_fer' => ['Mur de fer', 180, '#9aa0a6', 1, 1],
        'mur_crepusculaire' => ['Mur crépusculaire', 120, '#5a4a7a', 1, 1],
        'mur_blanc' => ['Mur blanc', 180, '#e8e8e8', 1, 1],
        'muret' => ['Muret', 40, '#999999', 1, 1],
        'barricade' => ['Barricade', 40, '#8b6d43', 1, 1],
        'table_bois' => ['Table en bois', 5, '#8b6d43', 1, 0],
    ];

    /**
     * Décor solide constructible par les joueurs : chaque type reçoit
     * sa pseudo-race (label, pv, bgColor, blocks_passage,
     * blocks_projectiles — haut : bloque la flèche, bas : elle passe
     * au-dessus). Les exemplaires POSÉS PAR L'ÉDITEUR restent des
     * map_walls : un décor ne devient une entité que lorsqu'un joueur
     * le construit (placestructure).
     */
    private const DECOR_TYPES = [
        'pilier' => ['Pilier Olympien', 10, '#b0aca0', 1, 1],
        'pilier_nain' => ['Pilier Nain', 10, '#b0aca0', 1, 1],
        'monolithe_flamboyant' => ['Monolithe flamboyant', 10, '#7a4a2a', 1, 1],
        'roue_a_aubes' => ['Roue à aubes', 10, '#8b6d43', 1, 1],
        'trone' => ['Trone', 25, '#c9a227', 1, 1],
        'statue_monstrueuse' => ['Statue Monstrueuse', 10, '#9a968a', 1, 1],
        'statue_ailee' => ['Statue Ailée', 10, '#9a968a', 1, 1],
        'statue_heroique' => ['Statue Héroïque', 10, '#9a968a', 1, 1],
        'statue_forestiere' => ['Statue forestière', 10, '#7a8a6a', 1, 1],
        'statue_noble' => ['Statue Noble', 10, '#9a968a', 1, 1],
        'statue_garde' => ['Statue de garde', 10, '#9a968a', 1, 1],
        'statue_servant' => ['Statue de servant', 10, '#9a968a', 1, 1],
        'statue_colosses' => ['Statue de colosses', 10, '#9a968a', 1, 1],
        'statue_gisant' => ['Statue de Gisant', 10, '#9a968a', 1, 0],
        'totem_crane' => ['Totem crane', 10, '#8b6d43', 1, 1],
        'totem_sauvage' => ['Totem sauvage', 10, '#8b6d43', 1, 1],
        'totem_magique' => ['Totem sauvage', 10, '#8b6d43', 1, 1],
        'piedestal' => ['Piédestal', 15, '#b0aca0', 1, 0],
        'piedestal_pierre' => ['Piédestal en Pierre', 10, '#b0aca0', 1, 0],
        'tonneau' => ['Tonneau', 5, '#8b6d43', 1, 0],
        'torche_sol' => ['Torche sur pied', 10, '#8b6d43', 1, 0],
        'lanternesurpied_geant' => ['Lanterne sur pied', 10, '#8b6d43', 1, 0],
        'tombe2' => ['Tombe', 10, '#8a8a8a', 1, 0],
        'coffre_bois' => ['Coffre en Bois', 1, '#8b6d43', 1, 0],
        'coffre_bois_petrifie' => ['Coffre en Bois Pétrifié', 1, '#7a6a58', 1, 0],
        'coffre_metal' => ['Coffre en Métal', 1, '#9aa0a6', 1, 0],
    ];

    /**
     * Catalogue des objets constructibles de l'ANCIEN système
     * (items type « structure », posés par feu build.php) : prix, race
     * et texte HISTORIQUES (datas prod), icône et libellé de la
     * nouvelle action construire_{nom}. Chaque entrée : l'objet
     * existant est basculé constructible (stats importées de son JSON
     * si besoin), sinon créé ; l'action placestructure est posée.
     *
     * Restent volontairement hors catalogue : altar (l'autel de prière
     * a un comportement de map_walls dédié) et echelle (transition de
     * niveau, pas une structure).
     */
    private const BUILDABLES = [
        'pilier' => [100, 'common', 'ra-tower',
            'Ériger un pilier', 'Un pilier en pierres blanches. Évidemment, les Olympians l\'ont créé avant que les Nains ne fassent le leur.'],
        'pilier_nain' => [20, 'common', 'ra-tower',
            'Ériger un pilier nain', 'Un beau pilié noir. Les Nains ont eu l\'idée avec les Olympiens, bien sûr.'],
        'monolithe_flamboyant' => [30, 'common', 'ra-rune-stone',
            'Dresser un monolithe flamboyant', 'Un monolithe gigantesque aux lettres flamboyantes.'],
        'roue_a_aubes' => [50, 'elfe', 'ra-tower',
            'Monter une roue à aubes', 'Démonstration de l\'ingéniosité des Elfes, ne n\'a en réalité aucune utilité que la magie ne puisse remplacer.'],
        'trone' => [80, 'common', 'ra-crown',
            'Installer un trône', 'Le siège d\'un roi. Est-il de fer ?'],
        'statue_monstrueuse' => [50, 'olympien', 'ra-tower',
            'Sculpter une statue monstrueuse', 'Une statue représentant une créature monstrueuse.'],
        'statue_ailee' => [50, 'elfe', 'ra-tower',
            'Sculpter une statue ailée', 'Une sculpture de pierre à l\'effigie d\'une Divinité.'],
        'statue_heroique' => [50, 'olympien', 'ra-tower',
            'Sculpter une statue héroïque', 'Une sculpture de pierre à l\'effigie d\'un Héro.'],
        'statue_forestiere' => [50, 'elfe', 'ra-tower',
            'Sculpter une statue forestière', 'Une statue représentant le lien entre les Elfes et leur forêt.'],
        'statue_noble' => [100, 'common', 'ra-tower',
            'Sculpter une statue noble', 'Une statue d\'un rare raffinement.'],
        'statue_garde' => [20, 'common', 'ra-tower',
            'Sculpter une statue de garde', 'Une statue à la gloire de la fierté des Nains.'],
        'statue_servant' => [20, 'common', 'ra-tower',
            'Sculpter une statue de servant', 'Une statue reprsentant le devoir.'],
        'statue_colosses' => [30, 'common', 'ra-tower',
            'Sculpter une statue des colosses', 'Une statue à la gloire de la force des Géants.'],
        'statue_gisant' => [50, 'olympien', 'ra-tombstone',
            'Sculpter une statue de gisant', 'Un tombeau à l\'effigie d\'un Héro disparu.'],
        'totem_crane' => [100, 'common', 'ra-rune-stone',
            'Dresser un totem crâne', 'Un totem terrifiant.'],
        'totem_sauvage' => [30, 'common', 'ra-rune-stone',
            'Dresser un totem sauvage', 'Un totem à l\'air sauvage.'],
        'totem_magique' => [30, 'common', 'ra-rune-stone',
            'Dresser un totem magique', 'Un totem au reflets magiques.'],
        'piedestal' => [50, 'olympien', 'ra-gem',
            'Poser un piédestal', 'Un piédestal (pluriel: piédestaux) est un support isolé qui sert à recevoir un buste ou un grand objet d\'art et d\'ornement.'],
        'piedestal_pierre' => [25, 'common', 'ra-gem',
            'Poser un piédestal de pierre', 'Un piédestal (pluriel: piédestaux) est un support isolé qui sert à recevoir un buste ou un grand objet d\'art et d\'ornement.'],
        'tonneau' => [10, 'common', 'ra-wooden-sign',
            'Installer un tonneau', 'Un tonneau. Vide... malheureusement.'],
        'torche_sol' => [20, 'common', 'ra-torch',
            'Planter une torche', 'Un torche permettant d\'éclairer une pièce.'],
        'lanternesurpied_geant' => [30, 'common', 'ra-candle',
            'Installer une lanterne sur pied', 'Un bel ouvrage accueillant le feu en son sein.'],
        'tombe2' => [20, 'common', 'ra-tombstone',
            'Ériger une tombe', 'En souvenir de ceux qui sont partis.'],
        'coffre_bois' => [50, 'common', 'ra-wooden-sign',
            'Poser un coffre en bois', 'Un coffre est un meuble fermé destiné à contenir ou protéger des objets, pouvant le cas échéant permettre leur transport.'],
        'coffre_bois_petrifie' => [75, 'elfe', 'ra-wooden-sign',
            'Poser un coffre pétrifié', 'Un coffre est un meuble fermé destiné à contenir ou protéger des objets, pouvant le cas échéant permettre leur transport.'],
        'coffre_metal' => [100, 'nain', 'ra-anvil',
            'Poser un coffre en métal', 'Un coffre est un meuble fermé destiné à contenir ou protéger des objets, pouvant le cas échéant permettre leur transport.'],
        'mur_noir' => [100, 'common', 'ra-tower',
            'Construire un mur noir', 'Un solide mur noir.'],
        'mur_bois_petrifie' => [100, 'common', 'ra-tower',
            'Construire un mur de bois pétrifié', 'Une solide structure en Bois Pétrifié.'],
        'mur_vegetal' => [100, 'common', 'ra-tower',
            'Faire pousser un mur végétal', 'Un solide mur de bois. Il semble vivant.'],
        'mur_fer' => [100, 'common', 'ra-tower',
            'Construire un mur de fer', 'Le mur le plus solide d\'Olympia. Mais que mettent-ils dedans ?'],
        'mur_crepusculaire' => [100, 'common', 'ra-tower',
            'Construire un mur crépusculaire', 'Un solide mur aux reflets lunineux.'],
        'muret' => [80, 'common', 'ra-tower',
            'Monter un muret', 'Une protection de fortune constituée de quelques pierres.'],
        'barricade' => [80, 'common', 'ra-tower',
            'Dresser une barricade', 'Une protection de fortune constituée de quelques planches.'],
    ];

    public function getDescription(): string
    {
        return 'Walls become building entities: wall pseudo-races, buildable items/actions, map_walls conversion';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        // --- 1. blocks_passage + pseudo-races -------------------------------
        $conn->executeStatement(
            'ALTER TABLE races
             ADD COLUMN IF NOT EXISTS blocks_passage TINYINT(1) NOT NULL DEFAULT 1,
             ADD COLUMN IF NOT EXISTS blocks_projectiles TINYINT(1) NOT NULL DEFAULT 1'
        );

        foreach (self::WALL_RACES as $name => [$label, $pv, $bgColor, $blocksPassage, $blocksProjectiles]) {
            $conn->executeStatement(
                "INSERT IGNORE INTO races
                    (code, name, label, description, playable, hidden, kind, structure_nature,
                     bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
                 VALUES (?, ?, ?, '', 0, 1, 'structure', 'obstacle', '', '#cd7f32', ?, ?, ?, 'black', '', '', ?)",
                [strtoupper($name), $name, $label, $blocksPassage, $blocksProjectiles, $bgColor, $pv]
            );
        }

        // --- 2. objets constructibles + actions -----------------------------
        // Les stats de ces objets sont en base (ItemsFromJson) ; le type
        // structure et le subtype de routage build.php n'ont plus de sens.
        $conn->executeStatement(
            "UPDATE items SET type = 'constructible', subtype = '', stats_in_db = 1
             WHERE name IN ('mur_pierre', 'table_bois', 'route')"
        );

        $this->createBuildAction($conn, 'construire_mur_pierre', 'ra-tower',
            'Construire un mur de pierre', 'Monte un solide mur de pierre sur une case libre adjacente.',
            'mur_pierre', 'placestructure', ['type' => 'mur_pierre']);
        $this->createBuildAction($conn, 'construire_table_bois', 'ra-wooden-sign',
            'Installer une table en bois', 'Installe une table en bois sur une case libre adjacente — on peut passer autour comme dessus.',
            'table_bois', 'placestructure', ['type' => 'table_bois']);
        $this->createBuildAction($conn, 'construire_route', 'ra-shoe-prints',
            'Aménager une route', 'Pave une case adjacente — courir y devient possible.',
            'route', 'placelayer', ['layer' => 'routes', 'name' => 'route']);

        // --- 2bis. pseudo-races du décor constructible ----------------------
        foreach (self::DECOR_TYPES as $name => [$label, $pv, $bgColor, $blocksPassage, $blocksProjectiles]) {
            $conn->executeStatement(
                "INSERT IGNORE INTO races
                    (code, name, label, description, playable, hidden, kind, structure_nature,
                     bleeds, wound_color, blocks_passage, blocks_projectiles, bgColor, color, faction, plan, pv)
                 VALUES (?, ?, ?, '', 0, 1, 'structure', 'obstacle', '', '#cd7f32', ?, ?, ?, 'black', '', '', ?)",
                [strtoupper($name), $name, $label, $blocksPassage, $blocksProjectiles, $bgColor, $pv]
            );
        }

        // --- 2ter. objets constructibles de l'ancien système ----------------
        foreach (self::BUILDABLES as $name => [$price, $race, $icon, $actionLabel, $text]) {
            $this->ensureBuildableItem($conn, $name, $price, $race, $text);
            $this->createBuildAction($conn, 'construire_' . $name, $icon, $actionLabel,
                $text, $name, 'placestructure', ['type' => $name]);
        }

        // --- 3. conversion des murs posés -----------------------------------
        $raceRows = $conn->fetchAllAssociative(
            "SELECT name, label, pv FROM races WHERE kind = 'structure'"
        );
        $races = [];
        foreach ($raceRows as $race) {
            $races[$race['name']] = $race;
        }

        $nextId = max(20000000, (int) $conn->fetchOne(
            'SELECT COALESCE(MAX(id) + 1, 20000000) FROM players WHERE id BETWEEN 20000000 AND 29999999'
        ));
        $nextDisplayId = (int) $conn->fetchOne(
            "SELECT COALESCE(MAX(display_id) + 1, 1) FROM players WHERE player_type = 'building'"
        );

        $webroot = dirname(__DIR__, 2);
        $converted = 0;

        foreach ($conn->fetchAllAssociative('SELECT id, name, player_id, coords_id, damages FROM map_walls') as $wall) {
            $broken = str_ends_with((string) $wall['name'], '_broken');
            $base = $broken ? substr((string) $wall['name'], 0, -7) : (string) $wall['name'];

            // Décor éditeur : sa race existe (constructible) mais seuls
            // les exemplaires CONSTRUITS par un joueur naissent entités.
            if (!isset($races[$base]) || isset(self::DECOR_TYPES[$base])) {
                continue; // ressource, décor, mur spécial : reste un map_walls
            }

            // Un mur à damages ≥ pv aurait été détruit ; plancher à 1 PV.
            $currentPv = max(1, (int) $races[$base]['pv'] - max(0, (int) $wall['damages']));

            $avatar = $this->wallAvatar($webroot, $base, $broken);

            $conn->executeStatement(
                "INSERT INTO players
                    (id, player_type, display_id, name, race, avatar, portrait, coords_id, nextTurnTime, registerTime)
                 VALUES (?, 'building', ?, ?, ?, ?, ?, ?, 0, ?)",
                [$nextId, $nextDisplayId, $races[$base]['label'], $base, $avatar, $avatar,
                 (int) $wall['coords_id'], time()]
            );
            $conn->executeStatement(
                "INSERT INTO buildings (player_id, owner_id, faction, build_state) VALUES (?, ?, '', 'built')",
                [$nextId, $wall['player_id'] !== null ? (int) $wall['player_id'] : null]
            );
            if ($currentPv < (int) $races[$base]['pv']) {
                $conn->executeStatement(
                    "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)",
                    [$nextId, $currentPv - (int) $races[$base]['pv']]
                );
            }
            $conn->executeStatement('DELETE FROM map_walls WHERE id = ?', [(int) $wall['id']]);

            $nextId++;
            $nextDisplayId++;
            $converted++;
        }

        $this->write(sprintf('murs convertis en entités : %d', $converted));

        // Caches damier : ils embarquent les murs — tout régénérer.
        foreach (glob($webroot . '/datas/private/players/*.svg') ?: [] as $svg) {
            @unlink($svg);
        }
    }

    /**
     * Bascule un objet de l'ancien système en constructible : objet
     * existant → stats importées de son JSON historique si elles ne
     * sont pas déjà en base (mêmes règles que ItemStatsSeeder), puis
     * type constructible ; objet absent → créé depuis le catalogue.
     * Dans les deux cas, extra reçoit le nom d'affichage (label de la
     * pseudo-race) et l'image de mur en repli quand l'objet n'a pas
     * d'icône d'inventaire dédiée.
     */
    private function ensureBuildableItem(
        \Doctrine\DBAL\Connection $conn,
        string $name,
        int $price,
        string $race,
        string $text,
    ): void {
        $webroot = dirname(__DIR__, 2);
        $label = (string) ($conn->fetchOne('SELECT label FROM races WHERE name = ?', [$name]) ?: ucfirst($name));

        $extra = ['name' => $label];
        if (!is_file($webroot . '/img/items/' . $name . '.webp') && is_file($webroot . '/img/walls/' . $name . '.png')) {
            $extra['img'] = 'img/walls/' . $name . '.png';
            $extra['mini'] = 'img/walls/' . $name . '.png';
        }

        $item = $conn->fetchAssociative('SELECT id, private, stats_in_db, extra FROM items WHERE name = ?', [$name]);

        if ($item === false) {
            $conn->executeStatement(
                "INSERT INTO items (name, private, is_bankable, stats_in_db, price, type, subtype, race, text, extra)
                 VALUES (?, 0, 1, 1, ?, 'constructible', '', ?, ?, ?)",
                [$name, $price, $race, $text, json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );

            return;
        }

        if (!(int) $item['stats_in_db']) {
            // Import sans perte du JSON historique, façon ItemStatsSeeder.
            $dir = ((int) $item['private']) ? 'private' : 'public';
            $path = $webroot . '/datas/' . $dir . '/items/' . $name . '.json';
            $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            if (is_array($json)) {
                $set = ['stats_in_db = 1'];
                $params = [];
                foreach ($json as $key => $value) {
                    if (in_array($key, \App\Service\ItemStatsSeeder::SKIP_KEYS, true)) {
                        continue;
                    }
                    if (in_array($key, \App\Service\ItemStatsSeeder::SCALAR_KEYS, true)) {
                        $set[] = "`{$key}` = ?";
                        $params[] = is_numeric($value) ? $value : (string) $value;
                    } elseif (in_array($key, \App\Service\ItemStatsSeeder::JSON_KEYS, true)) {
                        $column = $key === 'addEffects' ? 'add_effects' : $key;
                        $set[] = "`{$column}` = ?";
                        $params[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        $extra[$key] = $value;
                    }
                }
                $params[] = (int) $item['id'];
                $conn->executeStatement('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
            }
        } elseif (!empty($item['extra'])) {
            $existing = json_decode((string) $item['extra'], true);
            if (is_array($existing)) {
                $extra = array_merge($extra, $existing);
            }
        }

        // Le nom d'affichage et l'image de repli du catalogue priment.
        $extra['name'] = $label;
        $conn->executeStatement(
            "UPDATE items SET type = 'constructible', subtype = '', extra = ? WHERE id = ?",
            [json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int) $item['id']]
        );
    }

    /**
     * Patron d'action « construire » (Version20260717200000) : conditions
     * TargetType/RequiresItem/RequiresTraitValue + un outcome à une
     * instruction de pose. Idempotent (skip si l'action existe).
     *
     * @param array<string, mixed> $instructionParams
     */
    private function createBuildAction(
        \Doctrine\DBAL\Connection $conn,
        string $actionName,
        string $icon,
        string $displayName,
        string $text,
        string $itemName,
        string $instructionType,
        array $instructionParams,
    ): void {
        if ($conn->fetchOne('SELECT id FROM actions WHERE name = ?', [$actionName]) !== false) {
            return;
        }

        $itemId = $conn->fetchOne('SELECT id FROM items WHERE name = ?', [$itemName]);
        if ($itemId === false) {
            $this->warnIf(true, "createBuildAction: objet {$itemName} absent, action {$actionName} non créée");
            return;
        }

        $conn->executeStatement(
            "INSERT INTO actions (name, icon, type, display_name, text, level) VALUES (?, ?, 'buff', ?, ?, 1)",
            [$actionName, $icon, $displayName, $text]
        );
        foreach ([
            ['TargetType', ['allowed' => ['character']], 0],
            ['RequiresItem', ['item' => (int) $itemId, 'n' => 1, 'consume' => true], 1],
            // BuildSite valide la case choisie AVANT tout paiement — sans
            // elle, une case volée consommerait l'objet pour rien.
            ['BuildSite', [], 2],
            ['RequiresTraitValue', ['a' => 1], 3],
        ] as [$type, $params, $order]) {
            $conn->executeStatement(
                'INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking)
                 SELECT ?, ?, id, ?, 1 FROM actions WHERE name = ?',
                [$type, json_encode($params), $order, $actionName]
            );
        }
        $conn->executeStatement(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT 'self', 'construction', 1, id FROM actions WHERE name = ?",
            [$actionName]
        );
        $conn->executeStatement(
            'INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT ?, ?, 0, o.id
             FROM action_outcomes o JOIN actions a ON a.id = o.action_id
             WHERE a.name = ? AND o.name = \'construction\'',
            [$instructionType, json_encode($instructionParams), $actionName]
        );
    }

    /** Même ordre de repli que BuildingService::resolveAvatar, variante brisée comprise. */
    private function wallAvatar(string $webroot, string $base, bool $broken): string
    {
        $candidates = [];
        if ($broken) {
            $candidates[] = 'img/walls/' . $base . '_broken.png';
        }
        $candidates[] = 'img/avatars/' . $base . '.webp';
        $candidates[] = 'img/walls/' . $base . '.png';

        foreach ($candidates as $candidate) {
            if (is_file($webroot . '/' . $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'WallsToStructures: pas de retour arrière (conversion de données).');
    }
}
