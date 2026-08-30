<?php
/**
 * Items catalog — mutations (POST only). Companion to admin/items.php.
 *
 * Routed on ?action: create | update. CSRF-validated, same menu level
 * as items.php (a direct POST can't bypass the dashboard gate), PRG
 * with a flash. `create` insère la ligne par son nom (clé naturelle,
 * unique) puis partage le chemin d'update complet — l'objet créé est
 * d'emblée stats_in_db=1, la base est sa source.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use Classes\Db;

(new AdminMenuAccessService())->enforce('items.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/items.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/items.php');
}

$action = $_GET['action'] ?? '';
if (!in_array($action, ['update', 'create', 'migrate-structure', 'delete', 'upload-image'], true)) {
    setFlash('warning', 'Action inconnue.');
    redirectTo('/admin/items.php');
}

// Suppression du catalogue : refusée tant qu'une table du jeu référence
// l'objet — le détail dit QUOI nettoyer d'abord. Les images restent sur
// disque (réutilisables si l'objet revient).
if ($action === 'delete') {
    $db = new Db();
    $id = (int) ($_POST['id'] ?? 0);
    $res = $db->exe('SELECT name FROM items WHERE id = ?', $id);
    $item = $res ? $res->fetch_object() : null;
    if ($item === null) {
        setFlash('warning', 'Objet inconnu.');
        redirectTo('/admin/items.php');
    }

    $references = [
        'players_items'             => 'exemplaire(s) en inventaire',
        'players_items_bank'        => 'exemplaire(s) en banque',
        'item_instances'            => 'instance(s) individualisée(s)',
        'map_items'                 => 'objet(s) au sol',
        'craft_recipes_ingredients' => 'recette(s) en ingrédient',
        'craft_recipes_results'     => 'recette(s) en résultat',
        'items_asks'                => 'ordre(s) de vente',
        'items_bids'                => "ordre(s) d'achat",
        'players_items_exchanges'   => 'échange(s) en cours',
    ];
    $blockers = [];
    foreach ($references as $table => $label) {
        $n = (int) ($db->exe("SELECT COUNT(*) AS n FROM {$table} WHERE item_id = ?", $id)->fetch_object()->n ?? 0);
        if ($n > 0) {
            $blockers[] = $n . ' ' . $label;
        }
    }
    if ($blockers !== []) {
        setFlash('warning', "Suppression impossible de « {$item->name} » : " . implode(', ', $blockers) . '.');
        redirectTo('/admin/items.php');
    }

    $db->exe('DELETE FROM items WHERE id = ?', $id);
    setFlash('success', "Objet « {$item->name} » supprimé du catalogue (images conservées sur disque).");
    redirectTo('/admin/items.php');
}

// Import d'une image d'objet : convertie au format de l'emplacement
// (webp objet/vignette, png carte), nom de fichier dicté par le nom
// technique — même clé que l'affichage en jeu.
if ($action === 'upload-image') {
    $db = new Db();
    $id = (int) ($_POST['id'] ?? 0);
    $res = $db->exe('SELECT name FROM items WHERE id = ?', $id);
    $item = $res ? $res->fetch_object() : null;
    if ($item === null) {
        setFlash('warning', 'Objet inconnu.');
        redirectTo('/admin/items.php');
    }
    $backTo = '/admin/items.php?action=edit&id=' . $id;

    $slots = [
        'item'        => ['img/items/' . $item->name . '.webp', 'webp'],
        'mini'        => ['img/items/' . $item->name . '_mini.webp', 'webp'],
        'wall'        => ['img/walls/' . $item->name . '.png', 'png'],
        'wall_broken' => ['img/walls/' . $item->name . '_broken.png', 'png'],
    ];
    $slot = (string) ($_POST['slot'] ?? '');
    if (!isset($slots[$slot])) {
        setFlash('warning', 'Emplacement d\'image inconnu.');
        redirectTo($backTo);
    }

    $file = $_FILES['image_file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_uploaded_file((string) $file['tmp_name'])) {
        setFlash('warning', 'Aucun fichier reçu (ou upload incomplet).');
        redirectTo($backTo);
    }
    if ((int) $file['size'] > 4 * 1024 * 1024) {
        setFlash('warning', 'Image trop lourde (4 Mo max).');
        redirectTo($backTo);
    }

    $info = @getimagesize((string) $file['tmp_name']);
    $mime = (string) ($info['mime'] ?? '');
    [$path, $format] = $slots[$slot];
    $destination = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;

    if ($mime === 'image/webp' && $format === 'webp') {
        // Déjà au bon format : copie telle quelle — indispensable quand le
        // GD local n'a aucun support webp (dev container), et sans perte
        // de toute façon.
        $written = move_uploaded_file((string) $file['tmp_name'], $destination);
    } elseif ($format === 'webp' && !function_exists('imagewebp')) {
        setFlash('warning', 'Cet environnement ne convertit pas en webp — téléversez directement un fichier .webp pour cet emplacement.');
        redirectTo($backTo);
    } else {
        $source = match ($mime) {
            'image/png'  => imagecreatefrompng((string) $file['tmp_name']),
            'image/jpeg' => imagecreatefromjpeg((string) $file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp')
                ? imagecreatefromwebp((string) $file['tmp_name'])
                : false,
            'image/gif'  => imagecreatefromgif((string) $file['tmp_name']),
            default      => false,
        };
        if ($source === false) {
            setFlash('warning', 'Image illisible (png, jpeg, webp ou gif attendu — webp non lisible sur cet environnement).');
            redirectTo($backTo);
        }

        // Transparence préservée à la conversion (sprites de carte détourés)
        if (!imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }
        imagealphablending($source, false);
        imagesavealpha($source, true);

        $written = $format === 'webp'
            ? imagewebp($source, $destination)
            : imagepng($source, $destination);
        imagedestroy($source);
    }

    if (!$written) {
        setFlash('danger', 'Écriture impossible : ' . $path);
        redirectTo($backTo);
    }

    setFlash('success', 'Image « ' . $path . ' » importée (' . (int) ($info[0] ?? 0) . '×' . (int) ($info[1] ?? 0) . ').');
    redirectTo($backTo);
}

// Migration d'un retardataire de l'ancien système de pose (type
// « structure ») vers le système actuel — bouton « Migrer » de la liste.
if ($action === 'migrate-structure') {
    $name = strtolower(trim((string) ($_POST['name'] ?? '')));
    try {
        $recap = (new \App\Service\StructureConversionService())->convertItem($name);
        setFlash('success', $recap);
    } catch (\Throwable $e) {
        setFlash('warning', 'Migration impossible : ' . $e->getMessage());
    }
    redirectTo('/admin/items.php');
}

$db = new Db();

if ($action === 'create') {
    $name = strtolower(trim((string) ($_POST['new_name'] ?? '')));
    if ($name === '' || !preg_match('/^[a-z0-9_\/-]+$/', $name)) {
        setFlash('warning', 'Nom d\'objet requis (minuscules, chiffres, _ / -).');
        redirectTo('/admin/items.php?action=new');
    }
    $exists = $db->exe('SELECT id FROM items WHERE name = ?', $name);
    if ($exists->num_rows) {
        setFlash('warning', "Un objet « {$name} » existe déjà.");
        redirectTo('/admin/items.php?action=new');
    }
    $db->exe('INSERT INTO items (name) VALUES (?)', $name);
    $id = (int) $db->exe('SELECT id FROM items WHERE name = ?', $name)->fetch_object()->id;
} else {
    $id = (int) ($_POST['id'] ?? 0);
    $res = $db->exe('SELECT id, name FROM items WHERE id = ?', $id);
    if (!$res->num_rows) {
        setFlash('warning', 'Objet introuvable.');
        redirectTo('/admin/items.php');
    }
    $name = $res->fetch_object()->name;
}

// Déclencheurs d'usure : whitelist stricte (source unique).
$triggers = array_values(array_intersect(
    \Classes\Item::WEAR_TRIGGERS,
    array_map('strval', (array) ($_POST['wear_triggers'] ?? []))
));

// Colonnes JSON : validées ou refusées — jamais de JSON cassé en base.
// add_effects en est sorti : il s'édite en lignes (effet + durée).
$jsonColumns = [];
foreach (array_diff(\Classes\Item::JSON_COLUMNS, ['add_effects']) as $col) {
    $raw = trim((string) ($_POST[$col] ?? ''));
    if ($raw === '') {
        $jsonColumns[$col] = null;
        continue;
    }
    if (json_decode($raw) === null && strtolower($raw) !== 'null') {
        setFlash('warning', "Champ {$col} : JSON invalide, rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
    $jsonColumns[$col] = $raw;
}

// Effets de consommation : les deux sélecteurs (appliqués / retirés,
// préfixe « - » historique), validés contre le catalogue des effets —
// une faute de frappe rendait la potion silencieuse. Recomposés dans
// extra.effet : le textarea Extra n'édite plus cette clé.
$effectService = new \App\Service\EffectService();

/**
 * Read the effect + duration rows of one field. An emptied name drops the
 * row; a blank duration means "unset", which each reader defaults its own
 * way. Keys the form does not edit travel back through the hidden field.
 *
 * @return list<array{name: string, duration: ?int, extra: array<string, mixed>}>
 */
$readEffectRows = static function (string $field) use ($effectService, $id): array {
    $rows = [];
    foreach (array_values((array) ($_POST[$field . '_name'] ?? [])) as $i => $rawName) {
        $effectName = strtolower(trim((string) $rawName));
        if ($effectName === '') {
            continue;
        }
        if (!$effectService->exists($effectName)) {
            setFlash('warning', "Effet inconnu du catalogue : « {$effectName} » — rien n'a été enregistré.");
            redirectTo('/admin/items.php?action=edit&id=' . $id);
        }
        $rawDuration = trim((string) ($_POST[$field . '_duration'][$i] ?? ''));
        $extra = json_decode((string) ($_POST[$field . '_extra'][$i] ?? ''), true);
        $rows[] = [
            'name' => $effectName,
            'duration' => $rawDuration === '' ? null : (int) $rawDuration,
            'extra' => is_array($extra) ? $extra : [],
        ];
    }

    return $rows;
};

// Effets d'arme au coup porté : lignes effet + durée, recomposées en JSON.
$strikeRows = $readEffectRows('strike_effects');
$strikeEffects = array_map(
    static fn (array $row): array => $row['extra'] + array_filter(
        ['name' => $row['name'], 'duration' => $row['duration']],
        static fn ($v): bool => $v !== null
    ),
    $strikeRows
);
$jsonColumns['add_effects'] = $strikeEffects === []
    ? null
    : json_encode(array_values($strikeEffects), JSON_UNESCAPED_UNICODE);

// Effets de consommation : les effets appliqués s'éditent en lignes (avec
// leur durée), les retirés restent un sélecteur — on ne règle pas la durée
// de ce qu'on dissipe. Recomposés dans extra.effet, préfixe « - »
// historique ; les durées dans extra.effetDuree, à côté et non dedans, pour
// qu'un lecteur qui les ignore continue de fonctionner.
$appliedRows = $readEffectRows('effets_appliques');
$consumeEffects = array_map(static fn (array $row): string => $row['name'], $appliedRows);
$consumeDurations = [];
foreach ($appliedRows as $row) {
    if ($row['duration'] !== null) {
        $consumeDurations[$row['name']] = $row['duration'];
    }
}
foreach ((array) ($_POST['effets_retires'] ?? []) as $effectName) {
    $effectName = strtolower(trim((string) $effectName));
    if ($effectName === '') {
        continue;
    }
    if (!$effectService->exists($effectName)) {
        setFlash('warning', "Effet inconnu du catalogue : « {$effectName} » — rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
    $consumeEffects[] = '-' . $effectName;
}

$extraObject = $jsonColumns['extra'] !== null ? json_decode($jsonColumns['extra']) : null;
if ($extraObject !== null && !is_object($extraObject)) {
    setFlash('warning', 'Champ extra : un objet JSON ({…}) est attendu — rien n\'a été enregistré.');
    redirectTo('/admin/items.php?action=edit&id=' . $id);
}
$extraObject ??= new stdClass();
unset($extraObject->effet, $extraObject->effetDuree);
if ($consumeEffects !== []) {
    $extraObject->effet = $consumeEffects;
}
if ($consumeDurations !== []) {
    $extraObject->effetDuree = (object) $consumeDurations;
}

// Graine : growTo / growZMin recomposés depuis les champs dédiés du
// formulaire (cron daily 20_grow_crops) — le textarea Extra n'édite
// plus ces clés. Ligne au nom vide = supprimée.
unset($extraObject->growTo, $extraObject->growZMin);
$growTo = [];
foreach (array_values((array) ($_POST['grow_name'] ?? [])) as $i => $growName) {
    $growName = trim((string) $growName);
    if ($growName === '') {
        continue;
    }
    $growTable = trim((string) ($_POST['grow_table'][$i] ?? ''));
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $growTable)) {
        setFlash('warning', "Pousse « {$growName} » : table cible invalide ({$growTable}) — rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
    $growTo[] = (object) [
        'name' => $growName,
        'table' => $growTable,
        'chance' => max(1, (int) ($_POST['grow_chance'][$i] ?? 1)),
    ];
}
if ($growTo !== []) {
    $extraObject->growTo = $growTo;
}
// Champ laissé VIDE : aucune contrainte de niveau (clé absente). Valeur 0 :
// contrainte « pas sous le niveau de la mer » — elle doit être stockée, d'où
// le test de chaîne vide et non un test sur l'entier (0 est une valeur).
$growZMin = trim((string) ($_POST['grow_z_min'] ?? ''));
if ($growZMin !== '') {
    $extraObject->growZMin = (int) $growZMin;
}

$jsonColumns['extra'] = get_object_vars($extraObject) !== []
    ? json_encode($extraObject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : null;

// Munitions : liste de noms d'objets, stockée en JSON — chaque nom doit
// exister au catalogue (une faute de frappe rendrait l'arme muette).
$munitions = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['munitions'] ?? '')))));
foreach ($munitions as $munition) {
    if (!$db->exe('SELECT id FROM items WHERE name = ?', $munition)->num_rows) {
        setFlash('warning', "Munition inconnue au catalogue : « {$munition} » — rien n'a été enregistré.");
        redirectTo('/admin/items.php?action=edit&id=' . $id);
    }
}

$set = [];
$params = [];

// Flags booléens — mêmes clés que les cases du formulaire (Item::FLAG_KEYS).
foreach (\Classes\Item::FLAG_KEYS as $flag) {
    $set[] = "`{$flag}` = ?";
    $params[] = (int) !empty($_POST[$flag]);
}

$set = array_merge($set, [
    'element = ?', 'spell = ?', 'exotique = ?',
    'wear_triggers = ?', 'wear_rate = ?', 'durability_max = ?', 'capacity = ?',
    'text = ?', 'price = ?', 'emplacement = ?', 'type = ?', 'subtype = ?', 'race = ?',
    'munitions = ?', 'add_effects = ?', 'forbid = ?', 'extra = ?',
    'stats_in_db = 1',
]);
$params = array_merge($params, [
    trim((string) ($_POST['element'] ?? '')),
    trim((string) ($_POST['spell'] ?? '')),
    trim((string) ($_POST['exotique'] ?? '')),
    implode(',', $triggers),
    max(0, (int) ($_POST['wear_rate'] ?? 0)),
    max(1, (int) ($_POST['durability_max'] ?? 100)),
    // '' = unlimited (NULL); a number is the content-line ceiling.
    trim((string) ($_POST['capacity'] ?? '')) === '' ? null : max(0, (int) $_POST['capacity']),
    trim((string) ($_POST['text'] ?? '')),
    max(0, (int) ($_POST['price'] ?? 1)),
    trim((string) ($_POST['emplacement'] ?? '')),
    trim((string) ($_POST['type'] ?? '')),
    trim((string) ($_POST['subtype'] ?? '')),
    trim((string) ($_POST['race'] ?? '')),
    $munitions === [] ? null : json_encode($munitions, JSON_UNESCAPED_UNICODE),
    $jsonColumns['add_effects'],
    $jsonColumns['forbid'],
    $jsonColumns['extra'],
]);

foreach (array_merge(\App\Enum\Caracs::KEYS, \Classes\Item::SPECIAL_KEYS) as $key) {
    $set[] = "`{$key}` = ?";
    $params[] = (int) ($_POST[$key] ?? 0);
}

$params[] = $id;
$db->exe('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?', $params);

setFlash('success', 'Objet « ' . $name . ' » enregistré — la base est sa source de vérité.');
redirectTo('/admin/items.php?action=edit&id=' . $id);
