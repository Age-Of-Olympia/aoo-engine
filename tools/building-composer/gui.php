<?php
/**
 * Building composer GUI - dev-only front end over compose.php.
 *
 * Served by the devcontainer Apache: http://localhost:9000/tools/building-composer/gui.php
 * Refused outside private networks: this is a workshop tool, not a game page.
 *
 * ?render=1&...   streams the composed PNG for the live preview
 * POST save       writes out/<name>.png + the 50x50 tiles
 */

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$private = $remote === '127.0.0.1' || $remote === '::1'
    || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $remote) === 1;
if (!$private) {
    http_response_code(403);
    exit('dev only');
}

require __DIR__ . '/compose.php';

const SHAPES = ['gable', 'hip', 'flat', 'temple', 'banque', 'attique'];

/** Artist swatches dropped in parts/ (templates excluded). */
function swatches(): array
{
    $files = glob(__DIR__ . '/parts/*.png') ?: [];

    return array_map('basename', $files);
}

/**
 * Building type names from the game catalog — the SAME rule as
 * admin/buildings.php and the Tiled palette (RaceService, kind Structure),
 * not a parallel query. Empty when the app or DB is away.
 */
function buildingTypes(): array
{
    static $types = null;
    if ($types !== null) {
        return $types;
    }
    $types = [];
    try {
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
        require_once dirname(__DIR__, 2) . '/config/db_constants.php';
        foreach ((new \App\Service\RaceService())->getRacesByKind(
            \App\Enum\EntityCategory::Structure->value
        ) as $race) {
            $types[] = $race->getName();
        }
        sort($types);
    } catch (Throwable) {
        $types = [];
    }

    return $types;
}

function param(string $key, array $allowed, ?string $default): ?string
{
    $value = $_REQUEST[$key] ?? '';

    return in_array($value, $allowed, true) ? $value : $default;
}

$form = param('form', array_keys(FORMS), 'maison_2x2');
$facade = param('facade', FACADES, 'stone');
$roof = param('roof', ROOFS, 'tiles');
$shape = param('shape', SHAPES, null);
$door = param('door', DOORS, 'simple');
$doorPos = param('door_pos', array_keys(DOOR_POS), 'centre');
$windows = param('windows', WINDOWS, 'simple');
$label = mb_substr(preg_replace('/[^A-Za-z0-9 \'\-|\n💰]/u', '', $_REQUEST['label'] ?? ''), 0, 34);
$facadeImg = param('facade_img', swatches(), null);
$roofImg = param('roof_img', swatches(), null);
$mirror = ($_REQUEST['mirror'] ?? '') === '1';
$seed = max(0, (int) ($_REQUEST['seed'] ?? 1));

if (isset($_GET['render'])) {
    $img = build($form, $facade, $roof, $shape,
        $facadeImg === null ? null : __DIR__ . '/parts/' . $facadeImg,
        $roofImg === null ? null : __DIR__ . '/parts/' . $roofImg,
        $mirror, $seed, $door, DOOR_POS[$doorPos], $windows, $label);
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    imagepng($img);
    exit;
}

$saved = null;
$error = null;
$action = $_POST['action'] ?? '';
if (in_array($action, ['save', 'building', 'foreground'], true)) {
    $name = preg_replace('/[^a-z0-9_-]/', '', $_POST['name'] ?? '');
    if ($name === '') {
        $error = 'nom de sortie vide';
    } else {
        $img = build($form, $facade, $roof, $shape,
            $facadeImg === null ? null : __DIR__ . '/parts/' . $facadeImg,
            $roofImg === null ? null : __DIR__ . '/parts/' . $roofImg,
            $mirror, $seed, $door, DOOR_POS[$doorPos], $windows, $label);
        $root = dirname(__DIR__, 2);
        if ($action === 'save') {
            @mkdir(__DIR__ . '/out');
            imagepng($img, __DIR__ . "/out/$name.png");
            cropTiles($img, __DIR__ . '/out', $name);
            $saved = "out/$name.png + tuiles";
        } elseif ($action === 'building') {
            // a chosen catalog type deliberately REPLACES its sprite; a free
            // name refuses to overwrite
            $type = $_POST['building_type'] ?? '';
            $asType = in_array($type, buildingTypes(), true);
            $file = $asType ? $type : $name;
            $target = "$root/img/walls/$file.png";
            $existed = file_exists($target);
            if ($existed && !$asType) {
                $error = "img/walls/$file.png existe déjà — choisir un autre nom ou un type";
            } elseif (@imagepng($img, $target)) {
                $saved = "img/walls/$file.png (bâtiment" . ($existed ? ', remplacé' : '') . ')';
            } else {
                $error = "échec d'écriture dans img/walls/ (droits ?)";
            }
        } else {
            $dir = "$root/img/foregrounds";
            if (file_exists("$dir/{$name}_00.png") || file_exists("$dir/_composed/$name.png")) {
                $error = "img/foregrounds/{$name}_* existe déjà — choisir un autre nom";
            } else {
                cropTiles($img, $dir, $name);
                @mkdir("$dir/_composed");
                @imagepng($img, "$dir/_composed/$name.png");
                if (file_exists("$dir/{$name}_00.png")) {
                    $saved = "img/foregrounds/{$name}_NN.png + _composed/$name.png";
                } else {
                    $error = "échec d'écriture dans img/foregrounds/ (droits ?)";
                }
            }
        }
    }
}

function options(array $values, ?string $selected, ?string $emptyLabel = null): string
{
    $html = $emptyLabel === null ? ''
        : '<option value="">' . htmlspecialchars($emptyLabel) . '</option>';
    foreach ($values as $value) {
        $sel = $value === $selected ? ' selected' : '';
        $html .= sprintf('<option%s>%s</option>', $sel, htmlspecialchars($value));
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Building composer</title>
<style>
  body { font: 13px/1.4 system-ui, sans-serif; margin: 0; display: flex; min-height: 100vh;
         background: #2b2b30; color: #ddd; }
  #controls { width: 470px; padding: 12px 16px; background: #232327;
              display: grid; grid-template-columns: 1fr 1fr; gap: 2px 14px;
              align-content: start; }
  #controls h1 { grid-column: 1 / -1; font-size: 16px; margin: 0 0 4px; color: #fff; }
  .wide { grid-column: 1 / -1; }
  label { display: block; margin: 7px 0 2px; color: #aaa; font-size: 11px;
          text-transform: uppercase; letter-spacing: .04em; }
  select, textarea, input[type=text], input[type=number] {
      width: 100%; padding: 4px 6px; background: #1a1a1d; color: #eee;
      border: 1px solid #444; border-radius: 4px; box-sizing: border-box;
      font: inherit; resize: none; }
  .actions { display: flex; gap: 6px; margin-top: 8px; }
  .actions button { flex: 1; padding: 5px 4px; }
  button { padding: 5px 12px; border: 0; border-radius: 4px;
           background: #4a6; color: #fff; font-weight: 600; cursor: pointer; }
  button.game { background: #47a; }
  .note { font-size: 11px; color: #888; margin-top: 8px; }
  .saved { color: #7c5; font-size: 12px; margin-top: 4px; }
  .err { color: #e75; font-size: 12px; margin-top: 4px; }
  #stage { flex: 1; display: flex; align-items: center; justify-content: center; }
  #preview { image-rendering: pixelated;
      background: repeating-conic-gradient(#3a3a40 0% 25%, #333338 0% 50%) 0 0/25px 25px;
      outline: 1px solid #555; }
  .zoom { display: flex; gap: 6px; }
  .zoom button { margin: 0; background: #38383e; font-weight: 400; }
  .zoom button.on { background: #4a6; }
</style>
</head>
<body>
<form id="controls" method="post">
  <h1>Building composer</h1>

  <div>
    <label>Forme</label>
    <select name="form"><?= options(array_keys(FORMS), $form) ?></select>
  </div>
  <div>
    <label>Façade</label>
    <select name="facade"><?= options(FACADES, $facade) ?></select>
  </div>
  <div>
    <label>Façade peinte (parts/)</label>
    <select name="facade_img"><?= options(swatches(), $facadeImg, '— procédurale —') ?></select>
  </div>
  <div>
    <label>Toit peint (parts/)</label>
    <select name="roof_img"><?= options(swatches(), $roofImg, '— procédural —') ?></select>
  </div>
  <div>
    <label>Toit (matériau)</label>
    <select name="roof"><?= options(ROOFS, $roof) ?></select>
  </div>
  <div>
    <label>Forme du toit</label>
    <select name="shape"><?= options(SHAPES, $shape, '— celle de la forme —') ?></select>
  </div>
  <div>
    <label>Porte</label>
    <select name="door"><?= options(DOORS, $door) ?></select>
  </div>
  <div>
    <label>Position porte</label>
    <select name="door_pos"><?= options(array_keys(DOOR_POS), $doorPos) ?></select>
  </div>
  <div>
    <label>Fenêtres</label>
    <select name="windows"><?= options(WINDOWS, $windows) ?></select>
  </div>
  <div>
    <label>Graine</label>
    <input type="number" name="seed" value="<?= $seed ?>" min="0">
  </div>
  <div>
    <label>Orientation</label>
    <select name="mirror">
      <option value="">normale</option>
      <option value="1"<?= $mirror ? ' selected' : '' ?>>miroir</option>
    </select>
  </div>
  <div>
    <label>Zoom</label>
    <div class="zoom" id="zoom">
      <button type="button" data-z="2">×2</button>
      <button type="button" data-z="4">×4</button>
      <button type="button" data-z="6">×6</button>
    </div>
  </div>
  <div class="wide">
    <label>Fond</label>
    <div class="zoom" id="bgsel">
      <button type="button" data-bg="damier">damier</button>
      <button type="button" data-bg="blanc">blanc</button>
      <button type="button" data-bg="gris">gris</button>
      <button type="button" data-bg="terre">terre</button>
    </div>
  </div>
  <div class="wide">
    <label>Texte (attique, 2 lignes max, 💰 = pièce d'or)</label>
    <textarea name="label" rows="2" maxlength="34"
              placeholder="BANQUE"><?= htmlspecialchars($label) ?></textarea>
  </div>
  <div>
    <label>Nom de sortie</label>
    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? 'composed') ?>"
           pattern="[a-z0-9_-]+">
  </div>
  <div>
    <label>Type (→ bâtiment)</label>
    <select name="building_type">
      <?= options(buildingTypes(), $_POST['building_type'] ?? null, '— nom libre —') ?>
    </select>
  </div>
  <div class="wide actions">
    <button type="submit" name="action" value="save">Enregistrer (out/)</button>
    <button type="submit" name="action" value="building" class="game" id="btnBuilding">→ Bâtiment</button>
    <button type="submit" name="action" value="foreground" class="game">→ Foreground</button>
  </div>
  <?php if ($saved !== null): ?><div class="saved wide">Écrit : <?= htmlspecialchars($saved) ?></div><?php endif; ?>
  <?php if ($error !== null): ?><div class="err wide"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="note wide">Enregistrer produit les deux usages : <code>&lt;nom&gt;.png</code>
  (sprite entier → bâtiment, convention img/walls, la vignette avatar s'en déduit) et
  les tuiles <code>&lt;nom&gt;_NN.png</code> (→ foreground, convention img/foregrounds).
  Les PNG déposés dans <code>parts/</code> apparaissent dans les listes « peinte » ;
  gabarits à peindre dans <code>parts/templates/</code>.</div>
</form>

<div id="stage"><img id="preview" alt="aperçu"></div>

<script>
(function () {
    'use strict';
    const form = document.getElementById('controls');
    const preview = document.getElementById('preview');
    const zoomBar = document.getElementById('zoom');
    const bgBar = document.getElementById('bgsel');
    const BACKGROUNDS = {
        damier: 'repeating-conic-gradient(#3a3a40 0% 25%, #333338 0% 50%) 0 0/25px 25px',
        blanc: '#ffffff',
        gris: '#7a7a80',
        terre: '#8a7a5c',
    };
    const params = new URLSearchParams(location.search);
    let zoom = +params.get('zoom') || 4;
    let bg = BACKGROUNDS[params.get('bg')] ? params.get('bg') : 'damier';

    function refresh() {
        const data = new FormData(form);
        data.delete('name');
        data.delete('action');
        const query = new URLSearchParams(data);
        query.set('zoom', zoom);
        query.set('bg', bg);
        history.replaceState(null, '', 'gui.php?' + query);
        query.set('render', '1');
        query.set('t', Date.now());
        preview.src = 'gui.php?' + query;
    }

    preview.addEventListener('load', function () {
        preview.style.width = (preview.naturalWidth * zoom) + 'px';
        preview.style.height = (preview.naturalHeight * zoom) + 'px';
    });
    function markZoom() {
        zoomBar.querySelectorAll('button')
            .forEach(b => b.classList.toggle('on', +b.dataset.z === zoom));
    }

    function applyBg() {
        preview.style.background = BACKGROUNDS[bg];
        bgBar.querySelectorAll('button')
            .forEach(b => b.classList.toggle('on', b.dataset.bg === bg));
    }

    function updateBuildingButton() {
        const type = form.elements['building_type'].value;
        const file = type || form.elements['name'].value || 'composed';
        document.getElementById('btnBuilding').textContent = '→ Bâtiment (' + file + '.png)';
    }

    form.addEventListener('change', function () {
        updateBuildingButton();
        refresh();
    });
    form.elements['name'].addEventListener('input', updateBuildingButton);
    updateBuildingButton();
    let labelTimer;
    form.querySelector('[name=label]').addEventListener('input', function () {
        clearTimeout(labelTimer);
        labelTimer = setTimeout(refresh, 400);
    });
    zoomBar.addEventListener('click', function (e) {
        if (!e.target.dataset.z) return;
        zoom = +e.target.dataset.z;
        markZoom();
        refresh();
    });
    bgBar.addEventListener('click', function (e) {
        if (!e.target.dataset.bg) return;
        bg = e.target.dataset.bg;
        applyBg();
        refresh();
    });
    markZoom();
    applyBg();
    refresh();
}());
</script>
</body>
</html>
