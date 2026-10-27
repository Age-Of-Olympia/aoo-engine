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
use App\Service\AccountService;
use App\Service\AdminMenuAccessService;
use App\Service\PlayerLookupService;
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
$access = new AdminMenuAccessService();
$access->enforce($access->forCharacter('players.sheet', $id));

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

/** Counters stored as `players` columns, edited as absolute values (floored at 0). */
const PLAYER_EDIT_COUNTERS = [
    'malus'   => 'Malus',
    'energie' => 'Énergie',
    'pf'      => 'PF',
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
            \App\Service\Map\BoardChanges::entity($id);
            $db->exe('UPDATE players SET coords_id = ? WHERE id = ?', array($coordsId, $id));
            (new \App\Service\Map\EntityCellService())->syncCells($id);
            \App\Service\Map\BoardChanges::cell($goCoords);
        }

        // Vitalités : le restant voulu devient un écart en players_bonus
        // (restant = carac + n). Écart nul → ligne supprimée, comme le
        // ménage de fin de tour.
        $healFull = !empty($_POST['heal_full']);
        foreach (PLAYER_EDIT_VITALS as $trait => $label) {
            if (!$healFull && (!isset($_POST['vitals'][$trait]) || $_POST['vitals'][$trait] === '')) {
                continue;
            }
            $max = (int) ($player->caracs->$trait ?? 0);
            $wanted = $healFull ? $max : (int) $_POST['vitals'][$trait];
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

        // Column names come from the PLAYER_EDIT_COUNTERS whitelist, never from the request.
        foreach (PLAYER_EDIT_COUNTERS as $column => $label) {
            $raw = $_POST['counters'][$column] ?? '';
            if ($raw === '' || (int) $raw === (int) $player->data->$column) {
                continue;
            }
            $db->exe("UPDATE players SET {$column} = ? WHERE id = ?", array(max(0, (int) $raw), $id));
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
                ? "« {$label} » appliqué sans limite de durée à « {$player->data->name} »."
                : "« {$label} » appliqué à « {$player->data->name} » pour "
                    . PlayerEffectService::describeRemaining($duration) . '.');
        } elseif (isset($_POST['effect_update'])) {
            // Direct overwrite: add_effect ignores a weaker re-application,
            // only updateEffectByPlayerId can shorten or weaken.
            if (!(new PlayerEffectService())->updateEffectByPlayerId($id, $effectName, $duration, $value)) {
                throw new RuntimeException("« {$label} » n'est plus appliqué à ce personnage.");
            }

            setFlash('success', "« {$label} » ajusté : valeur {$value}, "
                . (PlayerEffectService::isInfinite($duration)
                    ? 'sans limite de durée.'
                    : PlayerEffectService::describeRemaining($duration) . '.'));
        } else {
            $player->end_effect($effectName);
            setFlash('success', "« {$label} » retiré de « {$player->data->name} ».");
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

$canRemoveRank = $access->viewerAllows($access->forCharacter('players.rank-removal', $id));

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
                    'PI %+d : solde ramené à min(XP, plafond) − rangs payés.', $offset
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
                        . (int) $player->data->pi . '. Ajoutez de l\'XP d\'abord pour garder XP et PI cohérents.'
                    );
                }
                $player->put_upgrade($carac, $cost);
                setFlash('success', '+1 rang de ' . CARACS[$carac] . " pour {$cost} PI.");
            } else {
                if (!$canRemoveRank) {
                    throw new RuntimeException('Votre niveau d\'accès ne permet pas de retirer un rang.');
                }
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

/* Animation shortcuts (the « Animation » card): what the console did,
 * from the sheet. Each form posts its own marker field. */
$animationForms = [
    'tp_near' => function () use ($player, $id): string {
        $matches = (new PlayerLookupService())->resolve((string) ($_POST['near'] ?? ''), ['real', 'npc']);
        if (count($matches) !== 1) {
            throw new \InvalidArgumentException(count($matches) === 0
                ? 'Aucun personnage ne porte ce nom ou ce matricule.'
                : 'Plusieurs personnages portent ce nom : utilisez le matricule.');
        }
        $other = PlayerFactory::legacy($matches[0]['id']);
        $other->get_data(false);
        $goCoords = $other->getCoords();
        if ($goCoords === null) {
            throw new \InvalidArgumentException($other->data->name . ' n\'est pas sur le plateau.');
        }
        // Same as the console « tp <nom> <autre> »: the nearest free cell around the other one.
        $goCoords->coordsId = View::get_free_coords_id_arround($goCoords);
        $player->go($goCoords);
        \App\Service\Map\BoardChanges::viewer($id);

        return $player->data->name . ' est à côté de ' . $other->data->name . '.';
    },
    'missive_send' => function () use ($player, $id): string {
        $title = trim((string) ($_POST['missive_title'] ?? ''));
        $text = trim((string) ($_POST['missive_text'] ?? ''));
        if ($title === '' || $text === '') {
            throw new \InvalidArgumentException('Le titre et le message sont obligatoires.');
        }
        // Sent by the character the admin plays right now, who also receives the thread.
        $authorId = (int) $_SESSION['playerId'];
        (new \App\Service\MissiveService())->sendNewMissive($authorId, array_unique([$id, $authorId]), $title, $text);

        return 'Missive envoyée à ' . $player->data->name . '.';
    },
    'log_add' => function () use ($player): string {
        $text = trim((string) ($_POST['log_text'] ?? ''));
        if ($text === '') {
            throw new \InvalidArgumentException('La ligne de journal est vide.');
        }
        \Classes\Log::put($player, $player, $text, 'console');

        return 'Ligne ajoutée au journal de ' . $player->data->name . '.';
    },
    'log_delete_last' => function () use ($player, $id, $db): string {
        $db->exe('DELETE FROM players_logs WHERE player_id = ? ORDER BY time DESC LIMIT 1', $id);

        return 'Dernière ligne du journal de ' . $player->data->name . ' retirée.';
    },
];
foreach ($animationForms as $marker => $run) {
    if (isset($_POST[$marker])) {
        handleAdminPost($backTo, $run);
    }
}

$canBlock = (new AdminMenuAccessService())->viewerAllows('players.block');
if (isset($_POST['account_block'])) {
    handleAdminPost($backTo, function () use ($canBlock, $player, $id): string {
        if (!$canBlock) {
            throw new \InvalidArgumentException('Votre niveau d\'accès ne permet pas de bloquer un compte.');
        }
        $block = $_POST['account_block'] === '1';
        (new AccountService())->setBlocked($id, $block);

        return 'Compte de ' . $player->data->name . ($block ? ' bloqué.' : ' débloqué.');
    });
}

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
    . ' · ' . techLabel(character_race_choices()[(string) $player->data->race] ?? (string) $player->data->race, (string) $player->data->race)
    . ' · ' . (($player->data->player_type ?? 'real') === 'npc' ? 'PNJ' : 'Joueur') . '</p>');

$position = formCard('Position', '<div class="d-flex flex-wrap gap-2">'
    . formField('X', formInput('x', (string) $coords->x, 'type="number"'))
    . formField('Y', formInput('y', (string) $coords->y, 'type="number"'))
    . formField('Z', formInput('z', (string) $coords->z, 'type="number"'))
    . formField('Plan', formSelect('plan', plan_choices(), (string) $coords->plan))
    . '</div>'
    . '<p class="text-muted mb-0">Téléportation directe : aucune règle de déplacement appliquée.</p>');

$vitals = formCard('Vitalités restantes', ''
    . '<table class="table table-sm mb-2"><thead><tr><th></th><th>Max (caracs)</th><th>Restant</th></tr></thead>'
    . '<tbody>' . $vitalsRows . '</tbody></table>'
    . formCheckbox('heal_full', false, 'Tout remettre au maximum'));

$countersRows = '';
foreach (PLAYER_EDIT_COUNTERS as $column => $label) {
    $countersRows .= '<tr>'
        . '<th scope="row">' . e($label) . '</th>'
        . '<td><input type="number" min="0" class="form-control" style="max-width:8rem"'
        . ' name="counters[' . e($column) . ']" value="' . (int) $player->data->$column . '"></td>'
        . '</tr>';
}
$counters = formCard('Compteurs', ''
    . '<table class="table table-sm mb-0"><thead><tr><th></th><th>Valeur</th></tr></thead>'
    . '<tbody>' . $countersRows . '</tbody></table>');

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
        . '<td><form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex gap-1">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="carac" value="' . e($k) . '">'
        . '<button type="submit" name="upgrade_add" value="1" class="btn btn-sm btn-outline-primary"'
        . ' title="Acheter le rang suivant (' . $next . ' PI)">+1</button>'
        . ($owned > 0 && $canRemoveRank
            ? '<button type="submit" name="upgrade_remove" value="1" class="btn btn-sm btn-outline-danger"'
                . ' title="Retirer le rang le plus cher, coût remboursé en PI"'
                . ' data-confirm="' . e('Retirer un rang de ' . $short . ' ?') . '">−1</button>'
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
    $coherence = '<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between mb-2 gap-2">'
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
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end gap-2">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Écart d\'XP (±)', formInput('xp_delta', '', 'type="number" placeholder="+500" required style="max-width:8rem"'))
    . '<button type="submit" name="xp_add" value="1" class="btn btn-outline-primary mb-3">Appliquer</button>'
    . '</form>'
    . ($isPnj
        ? '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end gap-2">'
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
    . ' « −1 » retire le rang le plus cher et rembourse son coût ; XP et PI restent cohérents dans les deux sens.</p>');

// ----- Inventaire (piles ; formulaires séparés du formulaire principal) -----

$inventoryRows = '';
$stock = $db->exe(
    'SELECT pi.item_id, pi.n, pi.equiped, i.name, i.label FROM players_items pi
     JOIN items i ON i.id = pi.item_id WHERE pi.player_id = ? ORDER BY i.name',
    $id
);
while ($stockRow = $stock->fetch_object()) {
    $inventoryRows .= '<tr>'
        . '<td>' . techLabel((string) ($stockRow->label ?: $stockRow->name), (string) $stockRow->name)
        . ($stockRow->equiped !== '' ? ' <span class="badge badge-info">' . e($stockRow->equiped) . '</span>' : '') . '</td>'
        . '<td>×' . (int) $stockRow->n . '</td>'
        . '<td><form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex gap-1"'
        . ' data-confirm="' . e('Retirer « ' . ($stockRow->label ?: $stockRow->name) . ' » de l\'inventaire ?') . '">'
        . $csrf->renderTokenField()
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="item_id" value="' . (int) $stockRow->item_id . '">'
        . '<input type="number" class="form-control form-control-sm" name="n" value="1" min="1" max="' . (int) $stockRow->n . '" style="width:5rem">'
        . '<button type="submit" name="inventory_remove" value="1" class="btn btn-sm btn-outline-danger">Retirer</button>'
        . '</form></td>'
        . '</tr>';
}

$catalogNames = [];
// The browser suggests by display name and posts the code.
$catalog = $db->exe('SELECT name, label FROM items ORDER BY COALESCE(label, name)');
while ($catalogRow = $catalog->fetch_object()) {
    $catalogNames[$catalogRow->name] = (string) ($catalogRow->label ?: $catalogRow->name);
}

$inventory = formCard('Inventaire (piles)', ''
    . ($inventoryRows === ''
        ? '<p class="text-muted">Inventaire vide.</p>'
        : '<table class="table table-sm mb-2"><thead><tr><th>Objet</th><th>Qté</th><th></th></tr></thead>'
            . '<tbody>' . $inventoryRows . '</tbody></table>')
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end gap-2">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Objet', formInput('item_name', '', 'list="pe-items" placeholder="Tapez le nom de l\'objet" required'))
    . formField('Quantité', formInput('n', '1', 'type="number" min="1" style="max-width:6rem"'))
    . '<button type="submit" name="inventory_add" value="1" class="btn btn-outline-primary mb-3">Ajouter</button>'
    . '</form>'
    . renderDatalist('pe-items', $catalogNames)
    . '<p class="text-muted mb-0">Piles seulement : les exemplaires individualisés (durabilité) sont créés par le jeu,'
    . ' et les objets équipés se gèrent en jeu.</p>');

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
        . ' id="effect-row-' . e($carried->getName()) . '" class="d-flex align-items-center gap-1">'
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
        . '<td><div class="d-flex align-items-center gap-2">'
        . '<input type="number" class="form-control form-control-sm" name="duration" min="0"'
        . ' value="' . ($infinite ? '' : $endTime) . '" style="width:5rem"'
        . ' form="effect-row-' . e($carried->getName()) . '">'
        . '<label class="mb-0 text-nowrap"><input type="checkbox" name="duration_infinite" value="1"'
        . ($infinite ? ' checked' : '') . ' form="effect-row-' . e($carried->getName()) . '"> ∞</label>'
        . '</div></td>'
        . '<td>' . $updateForm
        . '<button type="submit" name="effect_update" value="1" class="btn btn-sm btn-outline-primary">Modifier</button>'
        . '<button type="submit" name="effect_remove" value="1" class="btn btn-sm btn-outline-danger"'
        . ' data-confirm="' . e('Retirer « ' . $effectService->getLabel($carried->getName()) . ' » ?') . '">Retirer</button>'
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
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="d-flex flex-wrap align-items-end gap-2">'
    . $csrf->renderTokenField()
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . formField('Effet', formSelect('effect_name', $effectOptions, null, '— choisir —', 'required'))
    . formField('Durée (tours)', formInput('duration', '1', 'type="number" min="0" style="max-width:6rem"'), 'form-group', '0 = expire au prochain tour')
    . formField('Sans fin', '<input type="checkbox" name="duration_infinite" value="1" class="form-check-input">', 'form-group', 'ignore la durée')
    . formField('Valeur', formInput('effect_value', '1', 'type="number" min="1" style="max-width:6rem"'),
        'form-group', 'multiplicateur : carac ±valeur, mods × valeur')
    . '<button type="submit" name="effect_add" value="1" class="btn btn-outline-primary mb-3">Appliquer</button>'
    . '</form>'
    . '<p class="text-muted mb-1">Appliquer un effet respecte les règles du catalogue, annulations comprises :'
    . ' un effet qui en annule un autre le retire, et est lui-même retiré si le personnage a déjà un effet qui l\'annule.</p>'
    . '<p class="text-muted mb-1">La durée est comptée en <strong>tours</strong> du joueur : elle diminue de 1 à'
    . ' chaque tour et l\'effet est retiré à zéro. « Sans fin » (∞) applique un effet permanent, à retirer à la main.</p>'
    . '<p class="text-muted mb-0">« Modifier » remplace la valeur et la durée de l\'effet en cours, sans appliquer les'
    . ' règles ci-dessus : c\'est le seul moyen d\'écourter ou d\'affaiblir un effet.</p>');

$authorName = (string) ((new Db())->exe('SELECT name FROM players WHERE id = ?', (int) $_SESSION['playerId'])->fetch_object()->name ?? '');
$animationForm = static fn (string $inner): string => '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="mb-3">'
    . $csrfField . '<input type="hidden" name="id" value="' . (int) $id . '">' . $inner . '</form>';

$animation = formCard('Animation', ''
    . $animationForm(formField('Téléporter à côté de', '<div class="input-group">'
        . formInput('near', '', 'placeholder="Nom ou matricule" required')
        . '<div class="input-group-append"><button type="submit" name="tp_near" value="1" class="btn btn-outline-primary">Téléporter</button></div></div>',
        'form-group mb-0', 'Sur la case libre la plus proche de ce personnage.'))
    . '<hr>'
    . $animationForm(formField('Écrire une missive', formInput('missive_title', '', 'placeholder="Titre" required'))
        . formTextarea('missive_text', '', 4, 'placeholder="Message" required')
        . '<div class="d-flex align-items-center gap-2 mt-2"><button type="submit" name="missive_send" value="1" class="btn btn-outline-primary">Envoyer</button>'
        . '<small class="text-muted">Envoyée par votre personnage actuel, ' . e($authorName) . ', qui reçoit aussi les réponses.</small></div>')
    . '<hr>'
    . $animationForm(formField('Ajouter au journal', '<div class="input-group">'
        . formInput('log_text', '', 'placeholder="Ce qui s\'est passé" required')
        . '<div class="input-group-append"><button type="submit" name="log_add" value="1" class="btn btn-outline-primary">Ajouter</button></div></div>',
        'form-group mb-2', 'Une ligne dans le journal du personnage, visible comme les autres évènements.'))
    . '<form method="post" action="player-edit.php?id=' . (int) $id . '" data-confirm="Retirer la dernière ligne du journal de ' . e($player->data->name) . ' ?">'
    . $csrfField . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . '<button type="submit" name="log_delete_last" value="1" class="btn btn-sm btn-outline-danger">Retirer la dernière ligne du journal</button></form>');

$account = '';
if (($player->data->player_type ?? 'real') === 'real') {
    $isBlocked = (new AccountService())->isBlocked($id);
    $account = formCard('Compte', '<div class="d-flex flex-wrap align-items-center gap-2">'
        . ($isBlocked
            ? '<span class="badge badge-danger">Bloqué</span>'
            : '<span class="badge badge-success">Ouvert</span>')
        . ($canBlock
            ? '<form method="post" action="player-edit.php?id=' . (int) $id . '" class="ml-auto"'
                . ' data-confirm="' . e(($isBlocked ? 'Débloquer' : 'Bloquer') . ' le compte de ' . $player->data->name . ' ?') . '">'
                . $csrfField . '<input type="hidden" name="id" value="' . (int) $id . '">'
                . '<button type="submit" name="account_block" value="' . ($isBlocked ? '0' : '1') . '" class="btn btn-sm '
                . ($isBlocked ? 'btn-outline-primary">Débloquer' : 'btn-outline-danger">Bloquer') . '</button></form>'
            : '')
        . '</div>'
        . '<p class="text-muted mb-0 mt-2">Bloquer met un « ! » devant le mot de passe et l\'adresse enregistrés,'
        . ' ferme la partie en cours et oublie les navigateurs mémorisés.</p>');
}

$body = '<form method="post" action="player-edit.php?id=' . (int) $id . '">'
    . $csrfField
    . '<input type="hidden" name="id" value="' . (int) $id . '">'
    . $identity . $position
    . '<div class="row"><div class="col-md-7">' . $vitals . '</div><div class="col-md-5">' . $counters . '</div></div>'
    . $turn
    . '<button type="submit" name="player_save" value="1" class="btn btn-primary">Enregistrer</button>'
    . '</form>'
    . $account
    . $animation
    . $inventory
    . $effects
    . $progression;

$isNpc = ($player->data->player_type ?? '') === 'npc';
echo admin_layout(e($player->data->name) . ' <span class="text-muted">(#' . (int) $id . ')</span>', $body, [
    'back' => $isNpc ? ['/admin/pnjs.php', 'PNJ'] : ['/admin/players.php', 'Personnages'],
    'actions' => ($isNpc ? embodyButton($id, $csrf->generateToken(), 'btn btn-primary') : '')
        . '<a class="btn btn-outline-secondary" href="/admin/player-skills.php?id=' . (int) $id . '">Compétences</a>',
    'scripts' => ['/admin/js/scroll-keeper.js'],
]);
