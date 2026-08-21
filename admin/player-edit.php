<?php
// admin/player-edit.php
/**
 * Character edit sheet (admin dashboard → Personnages → Compétences →
 * Éditer): name, position (teleport), remaining vitals (PV, PM, MVT, A,
 * Ae — written to players_bonus, the same mechanic as wounds and point
 * spending), turn reset, progression (XP, PI, carac ranks, XP/PI audit),
 * inventory and effects. Built for playtesting: wound a character, give
 * their points back, move them, adjust their progression — without
 * touching the database by hand.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Factory\PlayerFactory;
use App\Service\CsrfProtectionService;
use App\Service\EffectService;
use App\Service\PlayerCaracsService;
use App\Service\PlayerEffectService;
use App\Service\ProgressionService;
use Classes\Db;
use Classes\Str;
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

// Une entité rangée hors du monde n'a pas de case : le formulaire la montre
// vide, et lui en donner une la repose sur le plateau.
$coords = $player->getCoords() ?? (object) ['x' => '', 'y' => '', 'z' => '', 'plan' => ''];

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

        $moved = $x !== (int) $coords->x
            || $y !== (int) $coords->y
            || $z !== (int) $coords->z
            || $plan !== (string) $coords->plan;

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
            (new \App\Service\TurnService())->setNextTurnTime($id, time() - 1);
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
            $res = $db->exe('SELECT i.name, pi.n FROM players_items pi JOIN items i ON i.id = pi.item_id WHERE pi.player_id = ? AND pi.item_id = ? AND pi.slot = ""', array($id, $itemId));
            $row = $res ? $res->fetch_object() : null;
            if ($row === null) {
                throw new RuntimeException('Cet objet n\'est pas dans la pile du personnage.');
            }
            if ($n >= (int) $row->n) {
                $db->exe('DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND slot = ""', array($id, $itemId));
            } else {
                $db->exe('UPDATE players_items SET n = n - ? WHERE player_id = ? AND item_id = ? AND slot = ""', array($n, $id, $itemId));
            }
            setFlash('success', "-{$n} × {$row->name} pour « {$player->data->name} ».");
        }
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

// Effects: apply, adjust or lift an effect — the animation gesture (freeze
// a character, shorten a poison, remove a curse) without SQL.
//
// On passe par Player::add_effect et end_effect, PAS par un INSERT
// direct : ce sont eux qui convertissent la durée en échéance et qui
// appliquent les ANNULATIONS du catalogue (poser un effet lève ceux
// qu'il annule, et tombe s'il en subit un qui le contredit). Une
// écriture directe en base produirait des états que le jeu ne sait pas
// produire — exactement ce qu'un outil d'admin ne doit pas fabriquer.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['effect_add']) || isset($_POST['effect_update']) || isset($_POST['effect_remove']))) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        $effectService = new EffectService();
        $effectName = trim((string) ($_POST['effect_name'] ?? ''));

        // Validé AVANT l'appel : add_effect pose l'effet puis sort en
        // erreur si le nom est inconnu — il laisserait une ligne
        // orpheline derrière lui.
        if (!$effectService->exists($effectName)) {
            throw new RuntimeException("Effet inconnu au catalogue : « {$effectName} ».");
        }

        $label = $effectService->getLabel($effectName);

        /* Duration in TURNS. "Sans fin" is a checkbox rather than a
         * value to guess: zero means "expired at the next turn", the
         * inverse of the old convention. */
        $duration = empty($_POST['duration_infinite'])
            ? max(0, (int) ($_POST['duration'] ?? 1))
            : PlayerEffectService::DURATION_INFINITE;
        $value = max(1, (int) ($_POST['effect_value'] ?? 1));

        if (isset($_POST['effect_add'])) {
            $player->add_effect($effectName, $duration, $value);

            setFlash('success', PlayerEffectService::isInfinite($duration)
                ? "« {$label} » posé sans limite de durée sur « {$player->data->name} »."
                : "« {$label} » posé sur « {$player->data->name} » pour "
                    . PlayerEffectService::describeRemaining($duration) . '.');
        } elseif (isset($_POST['effect_update'])) {
            // Direct overwrite: add_effect ignores a weaker re-application,
            // only updateEffectByPlayerId can shorten or weaken.
            if (!(new PlayerEffectService())->updateEffectByPlayerId($id, $effectName, $duration, $value)) {
                throw new RuntimeException("« {$label} » n'est plus porté par ce personnage.");
            }

            setFlash('success', "« {$label} » ajusté : valeur {$value}, "
                . (PlayerEffectService::isInfinite($duration)
                    ? 'sans limite de durée.'
                    : PlayerEffectService::describeRemaining($duration) . '.'));
        } else {
            $player->end_effect($effectName);
            setFlash('success', "« {$label} » levé sur « {$player->data->name} ».");
        }
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
    }
    redirectTo($backTo); // PRG
}

// Progression: XP, PI and carac ranks — the console's "checkpi" audit,
// brought onto the sheet. XP goes through put_xp (PI follows the season
// cap, the rank is recomputed), ranks go through put_upgrade /
// remove_upgrade (PI is paid and refunded) — never a direct UPDATE, which
// the progression table would contradict on the next read.

/** PI offset = min(XP, season cap) − PI − cost of purchased ranks. */
$piOffset = static function () use ($db, $player, $id): int {
    $paid = (int) $db->exe(
        'SELECT COALESCE(SUM(cost), 0) AS total FROM players_upgrades WHERE player_id = ?',
        $id
    )->fetch_object()->total;

    return min((int) $player->data->xp, SEASON_XP) - (int) $player->data->pi - $paid;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['xp_add']) || isset($_POST['pi_fix']) || isset($_POST['pi_add'])
        || isset($_POST['upgrade_add']) || isset($_POST['upgrade_remove']))) {
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);

        if (isset($_POST['xp_add'])) {
            $delta = (int) ($_POST['xp_delta'] ?? 0);
            if ($delta === 0) {
                throw new RuntimeException('Écart d\'XP nul : rien à appliquer.');
            }
            // put_xp keeps PI tied to XP (CapPI) — reading the actual delta
            // before/after avoids re-deriving the formula here.
            $piBefore = (int) $player->data->pi;
            $player->put_xp($delta);
            $piDelta = (int) $player->data->pi - $piBefore;
            setFlash('success', sprintf(
                'XP %+d pour « %s » — PI %+d, rang recalculé.',
                $delta, $player->data->name, $piDelta
            ));
        } elseif (isset($_POST['pi_add'])) {
            // PNJ only: their XP and PI are legitimately decorrelated, so PI
            // is granted or taken directly. A player's PI must keep tracking
            // XP — adjust through XP or the offset fix instead.
            if (($player->data->player_type ?? 'real') !== 'npc') {
                throw new RuntimeException(
                    'L\'ajustement direct des PI est réservé aux PNJ — pour un joueur, passez par l\'XP ou la correction d\'écart.'
                );
            }
            $delta = (int) ($_POST['pi_delta'] ?? 0);
            if ($delta === 0) {
                throw new RuntimeException('Écart de PI nul : rien à appliquer.');
            }
            if ($delta > 0) {
                (new ProgressionService())->addPi($id, $delta);
            } elseif (!(new ProgressionService())->spendPi($id, -$delta)) {
                throw new RuntimeException(
                    'PI insuffisants : le PNJ en a ' . (int) $player->data->pi . '.'
                );
            }
            setFlash('success', sprintf('PI %+d pour « %s ».', $delta, $player->data->name));
        } elseif (isset($_POST['pi_fix'])) {
            // Always recomputed server-side: the displayed value may have
            // gone stale between render and click.
            $offset = $piOffset();
            if ($offset === 0) {
                setFlash('success', 'XP et PI déjà en phase, rien à corriger.');
            } else {
                (new ProgressionService())->addPi($id, $offset);
                setFlash('success', sprintf(
                    'PI %+d : le solde retrouve min(XP, plafond) − rangs payés.', $offset
                ));
            }
        } else {
            $carac = (string) ($_POST['carac'] ?? '');
            $caracsService = new PlayerCaracsService();
            if ($caracsService->getUpgradeProgress($carac) === null) {
                throw new RuntimeException("Caractéristique inconnue ou non achetable : « {$carac} ».");
            }
            $owned = (int) ($player->get_upgrades()->$carac ?? 0);

            if (isset($_POST['upgrade_add'])) {
                $cost = $caracsService->returnCost($carac, $owned);
                if (!(new ProgressionService())->spendPi($id, $cost)) {
                    throw new RuntimeException(
                        "PI insuffisants : le rang de " . CARACS[$carac] . " coûte {$cost}, le personnage en a "
                        . (int) $player->data->pi . '. Ajoutez de l\'XP d\'abord pour rester en phase.'
                    );
                }
                $player->put_upgrade($carac, $cost);
                setFlash('success', '+1 rang de ' . CARACS[$carac] . " pour {$cost} PI.");
            } else {
                if ($owned < 1) {
                    throw new RuntimeException('Aucun rang de ' . CARACS[$carac] . ' à retirer.');
                }
                $player->remove_upgrade($carac, 1);
                setFlash('success', '−1 rang de ' . CARACS[$carac] . ', coût remboursé en PI.');
            }
        }
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
    . formField('X', formInput('x', (string) $coords->x, 'type="number"'))
    . formField('Y', formInput('y', (string) $coords->y, 'type="number"'))
    . formField('Z', formInput('z', (string) $coords->z, 'type="number"'))
    . formField('Plan', formInput('plan', (string) $coords->plan))
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

// ----- Progression (forms separate from the main form) -----

$caracsService = new PlayerCaracsService();

$paidByCarac = [];
$paidRes = $db->exe(
    'SELECT name, COUNT(*) AS n, COALESCE(SUM(cost), 0) AS total
     FROM players_upgrades WHERE player_id = ? GROUP BY name',
    $id
);
while ($paidRow = $paidRes->fetch_object()) {
    $paidByCarac[$paidRow->name] = $paidRow;
}

$upgradeRows = '';
foreach (CARACS as $k => $short) {
    if ($caracsService->getUpgradeProgress($k) === null) {
        continue; // ae: read from equipment, never bought
    }
    $owned = (int) ($paidByCarac[$k]->n ?? 0);
    $paid = (int) ($paidByCarac[$k]->total ?? 0);
    $next = $caracsService->returnCost($k, $owned);

    $upgradeRows .= '<tr' . ($owned > 0 ? '' : ' class="text-muted"') . '>'
        . '<td><strong>' . e($short) . '</strong> <small class="text-muted">' . e(CARACS_TXT[$k] ?? '') . '</small></td>'
        . '<td>' . $owned . '</td>'
        . '<td>' . ($paid > 0 ? $paid . ' PI' : '—') . '</td>'
        . '<td>' . $next . ' PI</td>'
        . '<td><form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex" style="gap:.25rem">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="carac" value="' . e($k) . '">'
        . '<button type="submit" name="upgrade_add" value="1" class="btn btn-sm btn-outline-primary"'
        . ' title="Acheter le rang suivant (' . $next . ' PI)">+1</button>'
        . ($owned > 0
            ? '<button type="submit" name="upgrade_remove" value="1" class="btn btn-sm btn-outline-danger"'
                . ' title="Retirer le rang le plus cher, coût remboursé en PI"'
                . ' onclick="return confirm(\'Retirer un rang de ' . e($short) . ' ?\');">−1</button>'
            : '')
        . '</form></td>'
        . '</tr>';
}

// The console "checkpi" invariant: min(XP, cap) = PI + paid ranks. It only
// binds real characters — a PNJ's PI is granted directly, so the sheet
// shows the theoretical XP its PI represents instead of an offset warning.
$totalPaid = 0;
foreach ($paidByCarac as $paidRow) {
    $totalPaid += (int) $paidRow->total;
}
$isPnj = ($player->data->player_type ?? 'real') === 'npc';

$offset = $piOffset();
if ($isPnj) {
    $coherence = '<p class="mb-2"><span class="badge badge-info">PNJ : XP et PI décorrélés</span>'
        . ' <small class="text-muted">XP théorique des rangs achetés : <strong>' . $totalPaid . '</strong>'
        . ' · rangs + réserve de PI : <strong>' . ($totalPaid + (int) $player->data->pi) . '</strong>'
        . ' (1 PI = 1 XP sous le plafond de saison)</small></p>';
} elseif ($offset === 0) {
    $coherence = '<p class="mb-2"><span class="badge badge-success">XP et PI en phase</span>'
        . ' <small class="text-muted">min(XP, plafond de saison) = PI disponibles + coût des rangs achetés</small></p>';
} else {
    $coherence = '<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between mb-2" style="gap:.5rem">'
        . '<span>Écart de <strong>' . sprintf('%+d', $offset) . ' PI</strong> — attendu :'
        . ' PI disponibles = min(XP, ' . SEASON_XP . ') − coût des rangs achetés.</span>'
        . '<form method="post" action="player-edit.php?id=' . (int) $id . '">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<button type="submit" name="pi_fix" value="1" class="btn btn-sm btn-warning">'
        . 'Corriger (PI ' . sprintf('%+d', $offset) . ')</button>'
        . '</form></div>';
}

$progression = formCard('Progression (XP / PI)', ''
    . '<p class="mb-2">XP <strong>' . (int) $player->data->xp . '</strong>'
    . ' <small class="text-muted">/ plafond de saison ' . SEASON_XP . '</small>'
    . ' · Rang <strong>' . (int) $player->data->rank . '</strong>'
    . ' · PI disponibles <strong>' . (int) $player->data->pi . '</strong>'
    . ' · Points bonus <strong>' . (int) $player->data->bonus_points . '</strong></p>'
    . $coherence
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end" style="gap:.5rem">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Écart d\'XP (±)', formInput('xp_delta', '', 'type="number" placeholder="+500" required style="max-width:8rem"'))
    . '<button type="submit" name="xp_add" value="1" class="btn btn-outline-primary mb-3">Appliquer</button>'
    . '</form>'
    . ($isPnj
        ? '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end" style="gap:.5rem">'
            . $csrf->renderTokenField()
            . '<input type="hidden" name="id" value="' . (int) $id . '">'
            . formField('Écart de PI (±)', formInput('pi_delta', '', 'type="number" placeholder="+200" required style="max-width:8rem"'),
                'form-group', 'PNJ seulement : PI sans XP')
            . '<button type="submit" name="pi_add" value="1" class="btn btn-outline-primary mb-3">Appliquer</button>'
            . '</form>'
        : '')
    . '<table class="table table-sm mb-2"><thead><tr><th>Carac</th><th>Rangs achetés</th><th>PI payés</th>'
    . '<th>Rang suivant</th><th></th></tr></thead>'
    . '<tbody>' . $upgradeRows . '</tbody></table>'
    . '<p class="text-muted mb-1">L\'XP passe par le moteur : sous le plafond de saison, 1 XP ajoute 1 PI'
    . ' (et l\'inverse en retirant) ; le rang est recalculé.</p>'
    . '<p class="text-muted mb-0">« +1 » achète le rang au prix du barème en dépensant les PI ;'
    . ' « −1 » retire le rang le plus cher et rembourse son coût — l\'équilibre XP/PI tient dans les deux sens.</p>');

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

// ----- Effets (formulaires séparés du formulaire principal) -----

$effectService = new EffectService();

$effectRows = '';
foreach ($player->getEffects() as $carried) {
    $endTime = (int) $carried->getEndTime();
    /* Durée en TOURS : négatif = sans fin, zéro = terminé (retiré au
     * prochain tour par TurnProcessingService). */
    $infinite = PlayerEffectService::isInfinite($endTime);
    $expiredHint = $endTime === 0
        ? ' <small class="text-muted">(expiré : retiré au prochain tour)</small>'
        : '';

    // Value and duration are edited in place: the row's form carries
    // both buttons (Modifier overwrites, Lever removes).
    $updateForm = '<form method="post" action="player-edit.php?id=' . (int) $id . '"'
        . ' id="effect-row-' . e($carried->getName()) . '" class="d-flex align-items-center" style="gap:.25rem">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="effect_name" value="' . e($carried->getName()) . '">';

    $effectRows .= '<tr>'
        . '<td><span class="ra ' . e($effectService->getIcon($carried->getName())) . '"></span> '
        . e($effectService->getLabel($carried->getName()))
        . ' <code>' . e($carried->getName()) . '</code>' . $expiredHint . '</td>'
        . '<td><input type="number" class="form-control form-control-sm" name="effect_value" min="1"'
        . ' value="' . (int) $carried->getValue() . '" style="width:5rem"'
        . ' form="effect-row-' . e($carried->getName()) . '"></td>'
        . '<td><div class="d-flex align-items-center" style="gap:.5rem">'
        . '<input type="number" class="form-control form-control-sm" name="duration" min="0"'
        . ' value="' . ($infinite ? '' : $endTime) . '" style="width:5rem"'
        . ' form="effect-row-' . e($carried->getName()) . '">'
        . '<label class="mb-0 text-nowrap"><input type="checkbox" name="duration_infinite" value="1"'
        . ($infinite ? ' checked' : '') . ' form="effect-row-' . e($carried->getName()) . '"> ∞</label>'
        . '</div></td>'
        . '<td>' . $updateForm
        . '<button type="submit" name="effect_update" value="1" class="btn btn-sm btn-outline-primary">Modifier</button>'
        . '<button type="submit" name="effect_remove" value="1" class="btn btn-sm btn-outline-danger"'
        . ' onclick="return confirm(\'Lever « ' . e($effectService->getLabel($carried->getName())) . ' » ?\');">Lever</button>'
        . '</form></td>'
        . '</tr>';
}

$effectOptions = [];
foreach ($effectService->getAllEffects() as $catalogEffect) {
    $effectOptions[$catalogEffect->getName()] = $catalogEffect->getLabel() !== ''
        ? $catalogEffect->getLabel() . ' (' . $catalogEffect->getName() . ')'
        : $catalogEffect->getName();
}
asort($effectOptions);

$effects = formCard('Effets', ''
    . ($effectRows === ''
        ? '<p class="text-muted">Aucun effet en cours.</p>'
        : '<table class="table table-sm mb-2"><thead><tr><th>Effet</th><th>Valeur</th><th>Durée restante (tours)</th><th></th></tr></thead>'
            . '<tbody>' . $effectRows . '</tbody></table>')
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end" style="gap:.5rem">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Effet', formSelect('effect_name', $effectOptions, null, '— choisir —', 'required'))
    . formField('Durée (tours)', formInput('duration', '1', 'type="number" min="0" style="max-width:6rem"'), 'form-group', '0 = expire au prochain tour')
    . formField('Sans fin', '<input type="checkbox" name="duration_infinite" value="1" class="form-check-input">', 'form-group', 'ignore la durée')
    . formField('Valeur', formInput('effect_value', '1', 'type="number" min="1" style="max-width:6rem"'))
    . '<button type="submit" name="effect_add" value="1" class="btn btn-outline-primary mb-3">Poser</button>'
    . '</form>'
    . '<p class="text-muted mb-1">Poser un effet applique les règles du catalogue, annulations comprises :'
    . ' un effet qui en neutralise un autre le lève, et tombe lui-même s\'il en subit un qui le contredit.</p>'
    . '<p class="text-muted mb-1">La durée se compte en <strong>tours</strong> du joueur : elle perd un point à'
    . ' chaque tour et l\'effet tombe à zéro. « Sans fin » (∞) pose un effet que le temps n\'use pas —'
    . ' un trait permanent, qu\'il faut lever à la main.</p>'
    . '<p class="text-muted mb-0">« Modifier » écrase valeur et durée de l\'effet porté, sans repasser par les'
    . ' règles de pose — c\'est le seul chemin pour écourter ou affaiblir un effet en cours.</p>');

$body = '<form method="post" action="player-edit.php?id=' . (int) $id . '">'
    . $csrfField
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . $identity . $position . $vitals . $turn
    . '<button type="submit" name="player_save" value="1" class="btn btn-primary">Enregistrer</button> '
    . '<a class="btn btn-outline-secondary" href="players.php">Retour à la liste</a>'
    . '</form>'
    . $progression
    . $inventory
    . $effects;

echo admin_layout('Édition — ' . $player->data->name, renderFlashMessage() . $body, [
    'scripts' => ['/admin/js/scroll-keeper.js'],
]);
