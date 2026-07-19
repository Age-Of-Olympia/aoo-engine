<?php
// admin/player-edit.php
/**
 * Fiche d'édition d'un personnage (admin dashboard → Personnages →
 * Compétences → Éditer) : nom, position (téléportation), vitalités
 * restantes (PV, PM, MVT, A, Ae — écrites dans players_bonus, la même
 * mécanique que les blessures et la dépense de points) et remise à
 * disposition du tour. Pensé pour le playtest : blesser un personnage,
 * lui rendre ses points, le déplacer — sans toucher à la base à la main.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Factory\PlayerFactory;
use App\Service\CsrfProtectionService;
use Classes\Db;
use Classes\View;

$csrf = new CsrfProtectionService();
$db = new Db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id === 0) {
    setFlash('danger', 'Personnage inconnu.');
    redirectTo('players.php');
}

$player = PlayerFactory::legacy($id);
if (!$player->get_data(false)) {
    setFlash('danger', "Aucun personnage #{$id}.");
    redirectTo('players.php');
}
$player->get_caracs();
$player->getCoords();

$backTo = 'player-edit.php?id=' . $id;

/** Vitalités éditables : libellé + carac de référence. */
const PLAYER_EDIT_VITALS = [
    'pv'  => 'PV',
    'pm'  => 'PM',
    'mvt' => 'MVT',
    'a'   => 'A',
    'ae'  => 'Ae',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['player_save'])) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        // Nom
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Le nom ne peut pas être vide.');
        }
        if ($name !== $player->data->name) {
            $db->exe('UPDATE players SET name = ? WHERE id = ?', array($name, $id));
        }

        // Position — téléportation directe (les règles de déplacement ne
        // s'appliquent pas à l'admin), plan existant exigé pour ne pas
        // créer un plan fantôme sur une faute de frappe.
        $x = (int) ($_POST['x'] ?? 0);
        $y = (int) ($_POST['y'] ?? 0);
        $z = (int) ($_POST['z'] ?? 0);
        $plan = trim((string) ($_POST['plan'] ?? ''));

        $moved = $x !== (int) $player->coords->x
            || $y !== (int) $player->coords->y
            || $z !== (int) $player->coords->z
            || $plan !== (string) $player->coords->plan;

        if ($moved) {
            $known = $db->exe('SELECT 1 FROM coords WHERE plan = ? LIMIT 1', $plan);
            if (!$known || !$known->num_rows) {
                throw new RuntimeException("Plan inconnu : « {$plan} ».");
            }
            $goCoords = (object) ['x' => $x, 'y' => $y, 'z' => $z, 'plan' => $plan];
            $coordsId = View::get_coords_id($goCoords);
            if ($coordsId === null) {
                throw new RuntimeException('Coordonnées invalides.');
            }
            $db->exe('UPDATE players SET coords_id = ? WHERE id = ?', array($coordsId, $id));
            View::refresh_players_svg($goCoords);
        }

        // Vitalités : le restant voulu devient un écart en players_bonus
        // (restant = carac + n). Écart nul → ligne supprimée, comme le
        // ménage de fin de tour.
        foreach (PLAYER_EDIT_VITALS as $trait => $label) {
            if (!isset($_POST['vitals'][$trait]) || $_POST['vitals'][$trait] === '') {
                continue;
            }
            $wanted = (int) $_POST['vitals'][$trait];
            $max = (int) ($player->caracs->$trait ?? 0);
            $n = $wanted - $max;

            if ($n === 0) {
                $db->exe('DELETE FROM players_bonus WHERE player_id = ? AND name = ?', array($id, $trait));
            } else {
                $db->exe(
                    'INSERT INTO players_bonus (player_id, name, n) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE n = VALUES(n)',
                    array($id, $trait, $n)
                );
            }
        }

        // Tour : l'horloge repasse dans le passé, le prochain chargement
        // du jeu déclenche un nouveau tour (points, usure, effets).
        if (!empty($_POST['turn_now'])) {
            $db->exe('UPDATE players SET nextTurnTime = ? WHERE id = ?', array(time() - 1, $id));
        }

        setFlash('success', "Personnage « {$name} » enregistré.");
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

// Inventaire : ajouter/retirer des exemplaires de pile — le geste
// d'animation (donner une pioche, retirer un objet de test) sans SQL.
// Les instances individualisées ne se créent pas ici (elles naissent du
// jeu) ; le retrait ne touche que la pile.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['inventory_add']) || isset($_POST['inventory_remove']))) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        if (isset($_POST['inventory_add'])) {
            $itemName = strtolower(trim((string) ($_POST['item_name'] ?? '')));
            $n = max(1, (int) ($_POST['n'] ?? 1));
            $res = $db->exe('SELECT id, name FROM items WHERE name = ?', $itemName);
            $item = $res ? $res->fetch_object() : null;
            if ($item === null) {
                throw new RuntimeException("Objet inconnu au catalogue : « {$itemName} ».");
            }
            $db->exe(
                'INSERT INTO players_items (player_id, item_id, n, equiped) VALUES (?, ?, ?, "")
                 ON DUPLICATE KEY UPDATE n = n + VALUES(n)',
                array($id, (int) $item->id, $n)
            );
            setFlash('success', "+{$n} × {$item->name} pour « {$player->data->name} ».");
        } else {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $n = max(1, (int) ($_POST['n'] ?? 1));
            $res = $db->exe('SELECT i.name, pi.n FROM players_items pi JOIN items i ON i.id = pi.item_id WHERE pi.player_id = ? AND pi.item_id = ?', array($id, $itemId));
            $row = $res ? $res->fetch_object() : null;
            if ($row === null) {
                throw new RuntimeException('Cet objet n\'est pas dans la pile du personnage.');
            }
            if ($n >= (int) $row->n) {
                $db->exe('DELETE FROM players_items WHERE player_id = ? AND item_id = ?', array($id, $itemId));
            } else {
                $db->exe('UPDATE players_items SET n = n - ? WHERE player_id = ? AND item_id = ?', array($n, $id, $itemId));
            }
            setFlash('success', "-{$n} × {$row->name} pour « {$player->data->name} ».");
        }

        // le fragment d'inventaire est un cache par personnage
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/datas/private/players/' . $id . '.invent.html');
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

// ----- Affichage -----

$vitalsRows = '';
foreach (PLAYER_EDIT_VITALS as $trait => $label) {
    $max = (int) ($player->caracs->$trait ?? 0);
    $remaining = (int) $player->getRemaining($trait);
    $vitalsRows .= '<tr>'
        . '<th scope="row">' . e($label) . '</th>'
        . '<td>' . $max . '</td>'
        . '<td><input type="number" class="form-control" style="max-width:8rem"'
        . ' name="vitals[' . e($trait) . ']" value="' . $remaining . '"></td>'
        . '</tr>';
}

$nextTurn = (int) $player->data->nextTurnTime;
$turnInfo = $nextTurn > time()
    ? 'Prochain tour le ' . date('d/m/Y à H:i', $nextTurn) . '.'
    : 'Tour déjà disponible.';

$csrfField = $csrf->renderTokenField();

$identity = formCard('Identité', ''
    . formField('Nom', formInput('name', $player->data->name))
    . '<p class="text-muted mb-0">Matricule ' . (int) $player->id
    . ' · ' . e((string) $player->data->race)
    . ' · ' . e((string) ($player->data->player_type ?? 'real')) . '</p>');

$position = formCard('Position', '<div class="d-flex flex-wrap" style="gap:.5rem">'
    . formField('X', formInput('x', (string) $player->coords->x, 'type="number"'))
    . formField('Y', formInput('y', (string) $player->coords->y, 'type="number"'))
    . formField('Z', formInput('z', (string) $player->coords->z, 'type="number"'))
    . formField('Plan', formInput('plan', (string) $player->coords->plan))
    . '</div>'
    . '<p class="text-muted mb-0">Téléportation directe : aucune règle de déplacement appliquée.</p>');

$vitals = formCard('Vitalités restantes', ''
    . '<table class="table table-sm mb-2"><thead><tr><th></th><th>Max (caracs)</th><th>Restant</th></tr></thead>'
    . '<tbody>' . $vitalsRows . '</tbody></table>'
    . '<p class="text-muted mb-0">Le restant s\'écrit en <code>players_bonus</code> (blessure = PV restants réduits) ;'
    . ' un restant égal au max supprime l\'écart.</p>');

$turn = formCard('Tour', ''
    . '<p class="mb-2">' . e($turnInfo) . '</p>'
    . formCheckbox('turn_now', false, 'Rendre le tour disponible maintenant'));

// ----- Inventaire (piles ; formulaires séparés du formulaire principal) -----

$inventoryRows = '';
$stock = $db->exe(
    'SELECT pi.item_id, pi.n, pi.equiped, i.name FROM players_items pi
     JOIN items i ON i.id = pi.item_id WHERE pi.player_id = ? ORDER BY i.name',
    $id
);
while ($stockRow = $stock->fetch_object()) {
    $inventoryRows .= '<tr>'
        . '<td><code>' . e($stockRow->name) . '</code>'
        . ($stockRow->equiped !== '' ? ' <span class="badge badge-info">' . e($stockRow->equiped) . '</span>' : '') . '</td>'
        . '<td>×' . (int) $stockRow->n . '</td>'
        . '<td><form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex" style="gap:.25rem"'
        . ' onsubmit="return confirm(\'Retirer « ' . e($stockRow->name) . ' » de l\\\'inventaire ?\');">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="item_id" value="' . (int) $stockRow->item_id . '">'
        . '<input type="number" class="form-control form-control-sm" name="n" value="1" min="1" max="' . (int) $stockRow->n . '" style="width:5rem">'
        . '<button type="submit" name="inventory_remove" value="1" class="btn btn-sm btn-outline-danger">Retirer</button>'
        . '</form></td>'
        . '</tr>';
}

$catalogNames = [];
$catalog = $db->exe('SELECT name FROM items ORDER BY name');
while ($catalogRow = $catalog->fetch_object()) {
    $catalogNames[$catalogRow->name] = '';
}

$inventory = formCard('Inventaire (piles)', ''
    . ($inventoryRows === ''
        ? '<p class="text-muted">Inventaire vide.</p>'
        : '<table class="table table-sm mb-2"><thead><tr><th>Objet</th><th>Qté</th><th></th></tr></thead>'
            . '<tbody>' . $inventoryRows . '</tbody></table>')
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end" style="gap:.5rem">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Objet (nom technique)', formInput('item_name', '', 'list="pe-items" placeholder="pioche" required'))
    . formField('Quantité', formInput('n', '1', 'type="number" min="1" style="max-width:6rem"'))
    . '<button type="submit" name="inventory_add" value="1" class="btn btn-outline-primary mb-3">Ajouter</button>'
    . '</form>'
    . renderDatalist('pe-items', $catalogNames)
    . '<p class="text-muted mb-0">Piles seulement — les instances individualisées (durabilité) naissent du jeu ;'
    . ' les objets équipés se gèrent en jeu.</p>');

$body = '<form method="post" action="player-edit.php?id=' . (int) $id . '">'
    . $csrfField
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . $identity . $position . $vitals . $turn
    . '<button type="submit" name="player_save" value="1" class="btn btn-primary">Enregistrer</button> '
    . '<a class="btn btn-outline-secondary" href="players.php">Retour à la liste</a>'
    . '</form>'
    . $inventory;

echo admin_layout('Édition — ' . $player->data->name, renderFlashMessage() . $body);
