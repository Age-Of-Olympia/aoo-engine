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

$body = '<form method="post" action="player-edit.php?id=' . (int) $id . '">'
    . $csrfField
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . $identity . $position . $vitals . $turn
    . '<button type="submit" name="player_save" value="1" class="btn btn-primary">Enregistrer</button> '
    . '<a class="btn btn-outline-secondary" href="players.php">Retour à la liste</a>'
    . '</form>';

echo admin_layout('Édition — ' . $player->data->name, renderFlashMessage() . $body);
