<?php
/**
 * Tutorial NPCs Admin
 *
 * CRUD over the tutorial_npcs table — the configurable roster + placement
 * for tutorial NPCs (template Gaïa-style + dynamic enemy-style spawns).
 * Replaces the hardcoded Gaïa migration row + hardcoded dummy spawn
 * constants previously in TutorialResourceManager / TutorialConstants.
 */

require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use Classes\Db;
use App\Service\CsrfProtectionService;

$database = new Db();
$csrf = new CsrfProtectionService();

$action = $_GET['action'] ?? 'list';
$npcId = isset($_GET['id']) ? (int) $_GET['id'] : null;

/* Resolve list of versions + steps once for dropdowns. */
$versions = [];
$res = $database->exe("SELECT DISTINCT version FROM tutorial_steps WHERE is_active = 1 ORDER BY version");
while ($row = $res->fetch_assoc()) {
    $versions[] = $row['version'];
}
if (empty($versions)) {
    $versions = ['1.0.0'];
}

/* For the spawn_at_step_id dropdown — keyed by version. */
$stepsByVersion = [];
$res = $database->exe("SELECT id, version, step_id, title FROM tutorial_steps WHERE is_active = 1 ORDER BY version, step_number");
while ($row = $res->fetch_assoc()) {
    $stepsByVersion[$row['version']][] = $row;
}

$races = defined('RACES_EXT') ? RACES_EXT : ['nain', 'elfe', 'dieu', 'ame', 'humain', 'lutin'];

/* ---------------------- Form rendering helpers --------------------- */
function renderNpcForm(?array $npc, array $versions, array $stepsByVersion, array $races, string $csrfToken): string
{
    $isEdit = $npc !== null;
    $action = $isEdit ? "tutorial-npcs-save.php?id={$npc['id']}" : "tutorial-npcs-save.php";
    $title = $isEdit ? "Modifier NPC #{$npc['id']}" : "Nouveau NPC";

    $val = function (string $key, $default = '') use ($npc) {
        if (!$npc || !array_key_exists($key, $npc)) return $default;
        return $npc[$key];
    };
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    $stepOptionsByVersion = [];
    foreach ($stepsByVersion as $version => $steps) {
        $opts = '<option value="">-- À l\'ouverture de la session (au début) --</option>';
        foreach ($steps as $step) {
            $sel = ((int) $val('spawn_at_step_id') === (int) $step['id']) ? ' selected' : '';
            $opts .= sprintf(
                '<option value="%d"%s>%s — %s</option>',
                (int) $step['id'], $sel,
                $h($step['step_id']), $h($step['title'])
            );
        }
        $stepOptionsByVersion[$version] = $opts;
    }
    /* JSON inside a <script type="application/json"> block must NOT be
     * htmlspecialchars'd — JSON.parse would choke on &quot;. JSON_HEX_TAG
     * keeps `</script>` from breaking out of the tag. */
    $stepsJson = json_encode($stepOptionsByVersion, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

    $versionOpts = '';
    $currentVersion = $val('version', '1.0.0');
    foreach ($versions as $v) {
        $sel = ($v === $currentVersion) ? ' selected' : '';
        $versionOpts .= "<option value=\"{$h($v)}\"{$sel}>{$h($v)}</option>";
    }

    $raceOpts = '';
    $currentRace = strtolower((string) $val('race', 'nain'));
    foreach ($races as $r) {
        $sel = ($r === $currentRace) ? ' selected' : '';
        $raceOpts .= "<option value=\"{$h($r)}\"{$sel}>{$h(ucfirst($r))}</option>";
    }

    $modeTemplate = $val('spawn_mode') === 'template' ? ' selected' : '';
    $modeDynamic  = $val('spawn_mode') === 'dynamic'  ? ' selected' : '';
    $isActive     = ((int) $val('is_active', 1)) === 1 ? ' checked' : '';

    $idHidden = $isEdit ? "<input type=\"hidden\" name=\"id\" value=\"{$h($npc['id'])}\">" : '';

    return <<<HTML
<div class="card mb-4">
  <div class="card-header"><h2>{$h($title)}</h2></div>
  <div class="card-body">
    <form method="post" action="{$h($action)}">
      <input type="hidden" name="csrf_token" value="{$h($csrfToken)}">
      {$idHidden}

      <div class="row">
        <div class="col-md-3 form-group">
          <label>Version</label>
          <select class="form-control" name="version">{$versionOpts}</select>
        </div>
        <div class="col-md-3 form-group">
          <label>Rôle</label>
          <input type="text" class="form-control" name="role" value="{$h($val('role'))}" placeholder="guide, enemy, …" required>
          <small class="text-muted">Étiquette libre pour identifier le NPC dans l'admin.</small>
        </div>
        <div class="col-md-3 form-group">
          <label>Mode de spawn</label>
          <select class="form-control" name="spawn_mode" id="npc_spawn_mode" required>
            <option value="template"{$modeTemplate}>template (placé sur la carte au démarrage)</option>
            <option value="dynamic"{$modeDynamic}>dynamic (spawn relatif au joueur)</option>
          </select>
        </div>
        <div class="col-md-3 form-group">
          <label class="d-block">Actif</label>
          <input type="checkbox" name="is_active" value="1"{$isActive}> oui
        </div>
      </div>

      <div class="row">
        <div class="col-md-3 form-group">
          <label id="npc_x_label">X</label>
          <input type="number" class="form-control" name="x" value="{$h($val('x', 0))}">
        </div>
        <div class="col-md-3 form-group">
          <label id="npc_y_label">Y</label>
          <input type="number" class="form-control" name="y" value="{$h($val('y', 0))}">
        </div>
        <div class="col-md-3 form-group">
          <label>Énergie</label>
          <input type="number" class="form-control" name="energie" value="{$h($val('energie', 100))}">
        </div>
        <div class="col-md-3 form-group">
          <label>Race</label>
          <select class="form-control" name="race">{$raceOpts}</select>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 form-group">
          <label>Nom</label>
          <input type="text" class="form-control" name="name" value="{$h($val('name'))}" required>
        </div>
        <div class="col-md-6 form-group">
          <label>Faction</label>
          <input type="text" class="form-control" name="faction" value="{$h($val('faction'))}">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 form-group">
          <label>Avatar (URL)</label>
          <input type="text" class="form-control font-monospace" name="avatar" value="{$h($val('avatar'))}" required>
        </div>
        <div class="col-md-6 form-group">
          <label>Portrait (URL)</label>
          <input type="text" class="form-control font-monospace" name="portrait" value="{$h($val('portrait'))}" required>
        </div>
      </div>

      <div class="form-group">
        <label>Texte (description / fiche personnage)</label>
        <textarea class="form-control" name="text" rows="3">{$h($val('text'))}</textarea>
      </div>

      <div class="form-group" id="npc_spawn_step_wrapper">
        <label>Spawn à l'étape (dynamic uniquement)</label>
        <select class="form-control" name="spawn_at_step_id" id="npc_spawn_step_id"></select>
        <small class="text-muted">
          Pour les NPCs <code>dynamic</code> : étape qui déclenche l'apparition.
          Laisser vide = spawn dès le début de la session.
        </small>
      </div>

      <div class="form-group text-end">
        <a href="tutorial-npcs.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<script id="npc_steps_data" type="application/json">{$stepsJson}</script>
<script>
(function(){
    const stepsByVersion = JSON.parse(document.querySelector('script#npc_steps_data').textContent);
    const versionEl = document.querySelector('select[name="version"]');
    const stepEl = document.getElementById('npc_spawn_step_id');
    const modeEl = document.getElementById('npc_spawn_mode');
    const stepWrap = document.getElementById('npc_spawn_step_wrapper');
    const xLabel = document.getElementById('npc_x_label');
    const yLabel = document.getElementById('npc_y_label');

    function refreshSteps() {
        const v = versionEl.value;
        stepEl.innerHTML = stepsByVersion[v] || '<option value="">(aucune étape)</option>';
    }
    function refreshModeUI() {
        const isDynamic = modeEl.value === 'dynamic';
        stepWrap.style.display = isDynamic ? 'block' : 'none';
        xLabel.textContent = isDynamic ? 'X (offset depuis le joueur)' : 'X (absolu sur la carte)';
        yLabel.textContent = isDynamic ? 'Y (offset depuis le joueur)' : 'Y (absolu sur la carte)';
    }

    versionEl.addEventListener('change', refreshSteps);
    modeEl.addEventListener('change', refreshModeUI);
    refreshSteps();
    refreshModeUI();
})();
</script>
HTML;
}

/* ---------------------- Routing ---------------------- */

$content = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($flash) {
    $alertType = $flash['type'] === 'success' ? 'success' : 'danger';
    $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    $content .= "<div class=\"alert alert-{$alertType}\">{$msg}</div>";
}

$csrfToken = $csrf->generateToken();

if ($action === 'new') {
    $content .= renderNpcForm(null, $versions, $stepsByVersion, $races, $csrfToken);
} elseif ($action === 'edit' && $npcId) {
    $res = $database->exe("SELECT * FROM tutorial_npcs WHERE id = ?", [$npcId]);
    $npc = $res->fetch_assoc();
    if (!$npc) {
        $content .= "<div class=\"alert alert-danger\">NPC #{$npcId} introuvable</div>";
    } else {
        $content .= renderNpcForm($npc, $versions, $stepsByVersion, $races, $csrfToken);
    }
} else {
    /* List view */
    $content .= '<div class="d-flex justify-content-between mb-3">'
              . '<h2>NPCs du Tutoriel</h2>'
              . '<a href="tutorial-npcs.php?action=new" class="btn btn-primary">+ Nouveau NPC</a>'
              . '</div>';

    $rows = [];
    $res = $database->exe("
        SELECT npc.*, ts.step_id AS step_name, ts.title AS step_title
        FROM tutorial_npcs npc
        LEFT JOIN tutorial_steps ts ON ts.id = npc.spawn_at_step_id
        ORDER BY npc.version, npc.spawn_mode, npc.id
    ");
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    if (empty($rows)) {
        $content .= '<p class="text-muted">Aucun NPC configuré pour le tutoriel.</p>';
    } else {
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $content .= '<table class="table table-striped"><thead><tr>'
                  . '<th>ID</th><th>Version</th><th>Rôle</th><th>Mode</th>'
                  . '<th>X / Y</th><th>Nom</th><th>Race</th>'
                  . '<th>Spawn step</th><th>Actif</th><th>Actions</th>'
                  . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $modeBadge = $r['spawn_mode'] === 'template'
                ? '<span class="badge bg-info">template</span>'
                : '<span class="badge bg-warning text-dark">dynamic</span>';
            $stepCell = $r['step_name']
                ? "<code>{$h($r['step_name'])}</code><br><small class=\"text-muted\">{$h($r['step_title'])}</small>"
                : '<small class="text-muted">au démarrage</small>';
            $activeBadge = ((int) $r['is_active']) === 1
                ? '<span class="badge bg-success">oui</span>'
                : '<span class="badge bg-secondary">non</span>';
            $deleteForm = sprintf(
                '<form method="post" action="tutorial-npcs-save.php?action=delete" style="display:inline" onsubmit="return confirm(\'Supprimer ce NPC ?\');">'
                . '<input type="hidden" name="csrf_token" value="%s">'
                . '<input type="hidden" name="id" value="%d">'
                . '<button type="submit" class="btn btn-sm btn-danger">Supprimer</button>'
                . '</form>',
                $h($csrfToken), (int) $r['id']
            );
            $content .= sprintf(
                '<tr>'
                . '<td>%d</td><td>%s</td><td>%s</td><td>%s</td>'
                . '<td>%d, %d</td><td>%s</td><td>%s</td>'
                . '<td>%s</td><td>%s</td>'
                . '<td><a href="tutorial-npcs.php?action=edit&id=%d" class="btn btn-sm btn-secondary">Éditer</a> %s</td>'
                . '</tr>',
                (int) $r['id'], $h($r['version']), $h($r['role']), $modeBadge,
                (int) $r['x'], (int) $r['y'], $h($r['name']), $h($r['race']),
                $stepCell, $activeBadge,
                (int) $r['id'], $deleteForm
            );
        }
        $content .= '</tbody></table>';
    }
}

echo admin_layout('Tutorial NPCs', $content);
