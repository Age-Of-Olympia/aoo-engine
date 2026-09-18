<?php
// admin/element-composer.php
/**
 * Composeur d'éléments (admin → Cartes) : fabrique une tuile SVG animée à
 * partir de filtres SVG (bruit, dégradé de couleurs, déformation d'une
 * image de base, flou, masque rond) réglés à la souris, avec aperçu en
 * direct, et l'enregistre dans img/<couche>/<nom>.svg via TileAssetService.
 *
 * Le SVG garde ses réglages dans data-composer : un élément déjà composé
 * se recharge ici pour être retouché.
 */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/helpers.php';

use App\Service\CsrfProtectionService;
use App\Service\TileAssetService;
use App\Service\TileCatalogService;
use App\Service\TiledMapService;

$csrf = new CsrfProtectionService();
$service = new TileAssetService();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['svg_save'])) {
    $layer = trim((string) ($_POST['layer'] ?? 'elements'));
    try {
        $csrf->validateTokenOrFail($_POST['csrf_token'] ?? null);
        $name = trim((string) ($_POST['name'] ?? ''));
        $service->putSvg($layer, $name, (string) ($_POST['svg'] ?? ''), !empty($_POST['replace']));
        setFlash('success', "« {$name}.svg » enregistrée dans img/{$layer}/.");
        redirectTo('tile-assets.php?layer=' . urlencode($layer));
    } catch (Throwable $e) {
        setFlash('danger', $e->getMessage());
        redirectTo('element-composer.php');
    }
}

/* Images de base proposées à la déformation : les tuiles posables du sol et
 * des éléments. Inlinées en data: par le navigateur, car un SVG chargé en
 * <image> ne va rien chercher à l'extérieur. */
$catalog = (new TileCatalogService())->buildCatalog(['elements', 'tiles']);
$baseImages = array_filter(
    $catalog['images'],
    fn(string $path, string $key) => !str_ends_with($path, '.svg') && !str_starts_with($key, 'tiles/trans_'),
    ARRAY_FILTER_USE_BOTH
);
ksort($baseImages);

/* SVG déjà composés : leurs réglages voyagent dans data-composer. */
$root = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');
$composed = [];
foreach ($service->layers() as $layer) {
    foreach (glob($root . '/img/' . TiledMapService::layerImageDir($layer) . '/*.svg') ?: [] as $file) {
        $svg = @simplexml_load_string((string) file_get_contents($file));
        $params = $svg ? (string) $svg['data-composer'] : '';
        if ($params !== '') {
            $composed[$layer . '/' . pathinfo($file, PATHINFO_FILENAME)] = json_decode($params, true);
        }
    }
}

ob_start();
?>

<style>
.checker{background:repeating-conic-gradient(#d8d8d8 0 25%,#fff 0 50%) 0 0/16px 16px;}
.composer{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px;align-items:start;font-size:13px;}
.composer .card{margin:0;}
.composer-preview{position:sticky;top:12px;}
.composer .form-group{margin-bottom:8px;}
.composer label{font-size:12px;margin-bottom:2px;display:block;}
.composer-selects{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px 10px;}
.composer-sliders{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:2px 14px;}
.composer-sliders label{display:flex;justify-content:space-between;}
.composer-sliders input[type=range]{width:100%;margin:0;}
.composer-colors{display:flex;gap:10px;align-items:center;margin-top:6px;}
.composer-colors input[type=color]{width:48px;height:28px;padding:1px;}
.composer-previews{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start;}
.composer-previews small{display:block;color:#6c757d;}
@media (max-width:1100px){.composer{grid-template-columns:1fr;}.composer-preview{position:static;}}
</style>
<div class="container">
    <h3>Composeur d'éléments</h3>

    <?= renderFlashMessage() ?>

    <div class="alert alert-info" style="font-size: 13px; line-height: 1.5;">
        Une tuile SVG de 50×50 faite de filtres : un bruit coloré (eau, lave, brume…), ou une image de base
        déformée par ce bruit. L'animation est du SVG natif, sans JavaScript en jeu. Le fichier va dans
        <code style="display:inline">img/&lt;couche&gt;/&lt;nom&gt;.svg</code>. Un élément qui porte le
        nom d'un effet du catalogue l'applique au pas ; sans effet, c'est un décor. Un élément se pose
        tourné depuis la page Éléments : un seul fichier sert les quatre sens.
    </div>

    <div class="composer">
        <div class="card">
            <div class="card-body" id="composer">
                <div class="d-flex gap-2 flex-wrap mb-2 align-items-center">
                    <span class="text-muted">Préréglages :</span>
                    <?php foreach (['eau', 'lave', 'poison', 'brume', 'sang', 'feu', 'pierre', 'boue', 'glace', 'marais', 'neige', 'sable'] as $preset): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-preset="<?= $preset ?>"><?= ucfirst($preset) ?></button>
                    <?php endforeach; ?>
                    <?php if ($composed !== []): ?>
                        <select id="reload" class="form-control form-control-sm" style="width:auto;">
                            <option value="">Recharger un SVG composé…</option>
                            <?php foreach ($composed as $key => $params): ?>
                                <option value="<?= e($key) ?>"><?= e($key) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div class="composer-selects">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Image de base (déformée par le bruit)</label>
                        <select id="p-base" class="form-control form-control-sm">
                            <option value="">— aucune : bruit coloré seul —</option>
                            <?php foreach ($baseImages as $key => $path): ?>
                                <option value="<?= e($path) ?>"><?= e($key) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bruit</label>
                        <select id="p-style" class="form-control form-control-sm">
                            <option value="clouds">nuageux</option>
                            <option value="turbulence">turbulent</option>
                            <option value="streaks_h">stries horizontales</option>
                            <option value="streaks_v">stries verticales</option>
                            <option value="cells">cellules (aplats)</option>
                            <option value="cracks">fissures (veines)</option>
                            <option value="relief">relief (pierre)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Animation</label>
                        <select id="p-anim" class="form-control form-control-sm">
                            <option value="drift_h">dérive horizontale</option>
                            <option value="drift_v">dérive verticale</option>
                            <option value="drift_d">dérive diagonale</option>
                            <option value="sway">balancement (va-et-vient)</option>
                            <option value="flicker">scintillement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Lumière</label>
                        <select id="p-light" class="form-control form-control-sm">
                            <option value="none">aucune</option>
                            <option value="diffuse">relief (diffuse)</option>
                            <option value="specular">reflets (spéculaire)</option>
                            <option value="both">relief + reflets</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Forme</label>
                        <select id="p-shape" class="form-control form-control-sm">
                            <option value="square">carré</option>
                            <option value="round">rond</option>
                            <option value="blob">flaque irrégulière</option>
                            <option value="splash">éclaboussure</option>
                            <option value="drops">gouttes</option>
                            <option value="band_h">bande horizontale</option>
                            <option value="band_v">bande verticale</option>
                            <option value="corner">coin (angle)</option>
                        </select>
                    </div>
                </div>

                <div class="composer-sliders" id="sliders"></div>

                <div class="composer-colors">
                    <label class="mb-0">Couleur sombre</label>
                    <input id="p-colorA" type="color" class="form-control form-control-sm" value="#0b3d5c">
                    <label class="mb-0">Couleur claire</label>
                    <input id="p-colorB" type="color" class="form-control form-control-sm" value="#5fb7e8">
                </div>
            </div>
        </div>

        <div class="card composer-preview">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h5 class="card-title mb-0">Aperçu</h5>
                    <select id="bg" class="form-control form-control-sm" style="width:auto;" title="Sol derrière l'aperçu">
                        <option value="">damier</option>
                        <?php foreach ($baseImages as $key => $path): ?>
                            <?php if (str_starts_with($key, 'tiles/')): ?>
                                <option value="<?= e($path) ?>">sur <?= e(substr($key, 6)) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="composer-previews">
                    <div><div id="preview-inline-big" class="checker" style="width:150px;height:150px;border:1px solid #ddd;display:grid;grid-template-columns:repeat(3,50px);"></div>
                        <small>3×3 côte à côte</small></div>
                    <div><div id="preview-edges" class="checker" style="width:150px;height:150px;border:1px solid #ddd;"></div>
                        <small>bords sur le damier</small></div>
                    <div><div id="preview-inline" class="checker" style="width:50px;height:50px;border:1px solid #ddd;"></div>
                        <small>en ligne</small></div>
                    <div><img id="preview-img" class="checker" width="50" height="50" style="border:1px solid #ddd;" alt="">
                        <small>en image</small></div>
                </div>

                <form method="post" class="mt-3">
                    <?= $csrf->renderTokenField() ?>
                    <input type="hidden" name="svg" id="svg-out">
                    <div class="d-flex gap-2">
                        <div class="form-group" style="flex:1;">
                            <label>Nom (sans extension)</label>
                            <input type="text" class="form-control form-control-sm" name="name" id="name" required
                                   pattern="[a-zA-Z0-9_.-]+" placeholder="ex: eau">
                        </div>
                        <div class="form-group" style="width:110px;">
                            <label>Couche</label>
                            <?= formSelect('layer', array_combine($service->layers(), $service->layers()), 'elements', null,
                                'id="layer" class="form-control form-control-sm"') ?>
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="replace" value="1" id="replace">
                        <label class="form-check-label" for="replace">Remplacer l'existant (ses autres formats sont supprimés)</label>
                    </div>
                    <button type="submit" name="svg_save" value="1" class="btn btn-primary btn-sm">
                        <i class="fas fa-save"></i> Enregistrer le SVG
                    </button>
                </form>
            </div>
        </div>
    </div>

    <details class="card mt-3"><summary class="card-body py-2" style="cursor:pointer;font-size:13px;">Source SVG</summary>
        <pre id="svg-src" class="mb-0 px-3 pb-3" style="font-size:11px;max-height:20rem;overflow:auto;"></pre></details>
</div>

<script>
(function () {
    const SLIDERS = [
        ['freq',    'Grain du bruit',            0.005, 0.3, 0.005, 0.04],
        ['octaves', 'Détail (octaves)',          1,     5,   1,     3],
        ['seed',    'Graine',                    0,     99,  1,     1],
        ['alphaLo', 'Opacité des creux',         0,     1,   0.05,  0.7],
        ['alphaHi', 'Opacité des crêtes',        0,     1,   0.05,  1],
        ['scale',   'Déformation de la base',    0,     30,  1,     8],
        ['hue',     'Teinte de la base (°)',     0,     360, 5,     0],
        ['blur',    'Flou',                      0,     3,   0.1,   0],
        ['gamma',   'Contraste (gamma)',         0.3,   3,   0.1,   1],
        ['detail',  'Grain fin (2e bruit)',      0,     1,   0.05,  0],
        ['relief',  'Force de la lumière',       0.5,   8,   0.5,   2],
        ['edge',    'Bord doux / taille',        0,     1,   0.05,  0.4],
        ['speed',   'Animation (s, 0 = fixe)',   0,     30,  0.5,   8],
        ['opacity', 'Opacité globale',           0,     1,   0.05,  1],
    ];
    const PRESETS = {
        eau:    {style: 'streaks_h', anim: 'drift_h', freq: 0.04, octaves: 3, colorA: '#0a3550', colorB: '#3f9fd6', alphaLo: 0.8, alphaHi: 1, speed: 8, shape: 'square', blur: 0, gamma: 1, detail: 0.25, light: 'specular', relief: 2},
        lave:   {style: 'turbulence', anim: 'drift_v', freq: 0.05, octaves: 3, colorA: '#1c0200', colorB: '#ff4a00', alphaLo: 1, alphaHi: 1, speed: 14, shape: 'square', blur: 0.2, gamma: 0.55, detail: 0.35, light: 'diffuse', relief: 2.5},
        poison: {style: 'clouds',    anim: 'drift_d', freq: 0.08, octaves: 2, colorA: '#10360e', colorB: '#8ef04a', alphaLo: 0.55, alphaHi: 0.95, speed: 14, shape: 'blob', edge: 0.4, blur: 0, gamma: 1, detail: 0.3, light: 'specular', relief: 1.5},
        brume:  {style: 'clouds',    anim: 'drift_h', freq: 0.03, octaves: 4, colorA: '#c9d3dc', colorB: '#ffffff', alphaLo: 0.1, alphaHi: 0.9, speed: 16, shape: 'square', blur: 1, gamma: 1, detail: 0, light: 'none', relief: 2},
        sang:   {style: 'clouds',    anim: 'drift_h', freq: 0.1,  octaves: 2, colorA: '#3f0007', colorB: '#a80d1a', alphaLo: 0.85, alphaHi: 1, speed: 0, shape: 'splash', edge: 0.5, blur: 0.2, gamma: 1, detail: 0, light: 'specular', relief: 1},
        feu:    {style: 'streaks_v', anim: 'flicker', freq: 0.05, octaves: 3, colorA: '#7a1500', colorB: '#ffd23a', alphaLo: 0, alphaHi: 1, speed: 0.5, shape: 'round', edge: 0.5, blur: 0.3, gamma: 0.8, detail: 0.3, light: 'none', relief: 2},
        pierre: {style: 'clouds',    anim: 'drift_h', freq: 0.1,  octaves: 3, colorA: '#4a4640', colorB: '#a8a196', alphaLo: 1, alphaHi: 1, speed: 0, shape: 'square', blur: 0, gamma: 1, detail: 0.5, light: 'diffuse', relief: 4},
        boue:   {style: 'clouds',    anim: 'drift_h', freq: 0.06, octaves: 3, colorA: '#33230f', colorB: '#8a6a3a', alphaLo: 0.9, alphaHi: 1, speed: 0, shape: 'blob', edge: 0.3, blur: 0.3, gamma: 1.2, detail: 0.3, light: 'both', relief: 2},
        glace:  {style: 'cracks',    anim: 'drift_h', freq: 0.05, octaves: 2, colorA: '#bfe6ff', colorB: '#ffffff', alphaLo: 0.6, alphaHi: 0.95, speed: 0, shape: 'square', blur: 0.2, gamma: 1, detail: 0, light: 'specular', relief: 1},
        marais: {style: 'clouds',    anim: 'sway',    freq: 0.05, octaves: 3, colorA: '#1f2e12', colorB: '#6b7d33', alphaLo: 0.9, alphaHi: 1, speed: 6, shape: 'square', blur: 0.3, gamma: 1.1, detail: 0.4, light: 'specular', relief: 1.5},
        neige:  {style: 'clouds',    anim: 'drift_h', freq: 0.06, octaves: 3, colorA: '#dfe8f2', colorB: '#ffffff', alphaLo: 1, alphaHi: 1, speed: 0, shape: 'square', blur: 0.4, gamma: 1, detail: 0.2, light: 'diffuse', relief: 1.5},
        sable:  {style: 'streaks_h', anim: 'drift_h', freq: 0.12, octaves: 2, colorA: '#b8955a', colorB: '#eed7a3', alphaLo: 1, alphaHi: 1, speed: 0, shape: 'square', blur: 0, gamma: 1, detail: 0.6, light: 'diffuse', relief: 1.5},
    };
    const COMPOSED = <?= json_encode($composed, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const EDGE_DEFS = <?= json_encode(\Classes\View::elementEdgeDefs(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    const sliders = document.getElementById('sliders');
    SLIDERS.forEach(([key, label, min, max, step, value]) => {
        sliders.insertAdjacentHTML('beforeend',
            `<div class="form-group"><label><span>${label}</span><output for="p-${key}"></output></label>
             <input type="range" id="p-${key}" min="${min}" max="${max}" step="${step}" value="${value}"></div>`);
    });

    const ids = ['base', 'style', 'anim', 'shape', 'light', 'colorA', 'colorB'].concat(SLIDERS.map(s => s[0]));
    const el = id => document.getElementById('p-' + id);
    const read = () => Object.fromEntries(ids.map(id => [id, el(id).value]));
    const apply = params => ids.forEach(id => { if (params[id] !== undefined) el(id).value = params[id]; });

    const hex = c => [1, 3, 5].map(i => (parseInt(c.substr(i, 2), 16) / 255).toFixed(3));

    /* One data: URI per base image, fetched once — the SVG must stand alone. */
    const dataUris = {};
    async function baseDataUri(path) {
        if (!path) return '';
        if (!dataUris[path]) {
            const blob = await (await fetch('/' + path)).blob();
            dataUris[path] = await new Promise(r => { const f = new FileReader(); f.onload = () => r(f.result); f.readAsDataURL(blob); });
        }
        return dataUris[path];
    }

    /* Noise styles: how the turbulence is shaped before it is coloured.
     * Streaks stretch the frequency on one axis; cells posterize; cracks
     * threshold to thin bright veins; relief embosses. */
    /* The same transfer function on the three colour channels. */
    const rgb = attrs => `<feComponentTransfer in="g" result="g">${['R', 'G', 'B'].map(c => `<feFunc${c} ${attrs}/>`).join('')}</feComponentTransfer>`;
    const smear = dev => `<feGaussianBlur in="g" stdDeviation="${dev}" result="g"/>` + rgb('type="linear" slope="2.5" intercept="-0.75"');
    const STYLES = {
        clouds:    {type: 'fractalNoise', fx: 1,   fy: 1},
        /* Ridged look folded from fractal noise (|v-0.5|*2): Chrome's
         * stitching leaves a hairline seam on type="turbulence". */
        turbulence:{type: 'fractalNoise', fx: 1,   fy: 1, post: rgb('type="table" tableValues="1 0 1"')},
        /* Streaks: an isotropic noise smeared along one axis (a stretched
         * frequency does not stitch cleanly in Chrome), then re-contrasted. */
        streaks_h: {type: 'fractalNoise', fx: 1.5, fy: 1.5, post: smear('8 0')},
        streaks_v: {type: 'fractalNoise', fx: 1.5, fy: 1.5, post: smear('0 8')},
        cells:     {type: 'fractalNoise', fx: 1,   fy: 1, post: rgb('type="discrete" tableValues="0 .25 .5 .75 1"')},
        /* Cracks: fractal noise sits around 0.5, so stretch the top of its
         * range before the threshold, or nothing crosses it. */
        cracks:    {type: 'fractalNoise', fx: 1,   fy: 1, post: rgb('type="linear" slope="4" intercept="-2"') + rgb('type="discrete" tableValues="0 1"')},
        relief:    {type: 'fractalNoise', fx: 1,   fy: 1, post: '<feConvolveMatrix in="g" result="g" order="3" kernelMatrix="-2 -1 0 -1 1 1 0 1 2" preserveAlpha="true"/>'},
    };

    /* Shape = a <mask>: white shows, black hides. The random ones carve
     * their edge from a second noise, seeded like the texture, so the
     * seed slider rolls the outline too. */
    function maskFor(p) {
        const soft = (+p.edge).toFixed(2), hard = (1 - p.edge).toFixed(2);
        const radial = `<radialGradient id="rg"><stop offset="${hard}" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>`;
        const edgeNoise = freq => `<feTurbulence type="fractalNoise" baseFrequency="${freq}" numOctaves="2" seed="${+p.seed + 7}" result="e"/>`;
        /* Luminance of the edge noise to alpha, stretched so about a third
         * of the tile survives the threshold. */
        const cut = (slope) => `<feColorMatrix in="e" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  1 0 0 0 0" result="e"/>
            <feComponentTransfer in="e" result="e"><feFuncA type="linear" slope="${slope}" intercept="${(-slope / 2 + 0.5 + +p.edge * 0.6).toFixed(2)}"/></feComponentTransfer>
            <feComposite in="e" in2="SourceGraphic" operator="in"/>`;
        switch (p.shape) {
            case 'round':
                return `<mask id="m">${radial}<rect width="50" height="50" fill="url(#rg)"/></mask>`;
            case 'blob':
                return `<mask id="m">${radial}<filter id="mf" x="-0.3" y="-0.3" width="1.6" height="1.6">${edgeNoise(0.06)}<feDisplacementMap in="SourceGraphic" in2="e" scale="${(8 + p.edge * 20).toFixed(0)}" xChannelSelector="R" yChannelSelector="G"/></filter><rect width="50" height="50" fill="url(#rg)" filter="url(#mf)"/></mask>`;
            case 'splash':
                return `<mask id="m">${radial}<filter id="mf">${edgeNoise(0.07)}${cut(6)}</filter><rect width="50" height="50" fill="url(#rg)" filter="url(#mf)"/></mask>`;
            case 'drops':
                return `<mask id="m"><filter id="mf">${edgeNoise(0.16)}${cut(8)}</filter><rect width="50" height="50" fill="#fff" filter="url(#mf)"/></mask>`;
            case 'band_h':
            case 'band_v':
                const axis = p.shape === 'band_h' ? 'x1="0" y1="0" x2="0" y2="1"' : 'x1="0" y1="0" x2="1" y2="0"';
                return `<mask id="m"><linearGradient id="lg" ${axis}><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset="${(soft / 2).toFixed(2)}" stop-color="#fff"/><stop offset="${(1 - soft / 2).toFixed(2)}" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient><rect width="50" height="50" fill="url(#lg)"/></mask>`;
            case 'corner':
                return `<mask id="m"><radialGradient id="rg" cx="0" cy="0" r="1"><stop offset="${hard}" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient><rect width="50" height="50" fill="url(#rg)"/></mask>`;
            default:
                return '';
        }
    }

    function build(p, baseUri) {
        const a = hex(p.colorA), b = hex(p.colorB);
        const st = STYLES[p.style] || STYLES.clouds;
        const f = +p.freq, dur = +p.speed;
        const fx = (f * st.fx).toFixed(4), fy = (f * st.fy).toFixed(4);

        /* Tiles sit side by side: the noise is stitched to the 50-unit
         * tile, so it is periodic, then tiled over a region three tiles
         * wide. Blur, relief and drift then read the neighbour a tile
         * would have, and nothing fades at the edge. The drift scrolls one
         * whole period, so the loop is seamless too. Flicker jumps seeds. */
        const flicker = dur > 0 && p.anim === 'flicker'
            ? `<animate attributeName="seed" values="${[0, 1, 2, 3, 4, 5].map(i => +p.seed + i).join(';')}" calcMode="discrete" dur="${dur}s" repeatCount="indefinite"/>`
            : '';
        /* The texture moves by a transform on its group, never by feOffset:
         * Chrome snaps feOffset to whole pixels, which jerks. The filtered
         * rect is three tiles wide, so a move of one period (drift) or a
         * few units there and back (sway, eased, two axes out of phase)
         * never uncovers the tile. */
        const slide = {drift_h: '50 0', drift_v: '0 50', drift_d: '50 50'}[p.anim];
        const ease = 'keyTimes="0;0.5;1" calcMode="spline" keySplines="0.45 0 0.55 1;0.45 0 0.55 1"';
        let move = '';
        if (dur > 0 && slide) {
            move = `<animateTransform attributeName="transform" type="translate" values="0 0;${slide}" dur="${dur}s" repeatCount="indefinite"/>`;
        } else if (dur > 0 && p.anim === 'sway') {
            move = `<animateTransform attributeName="transform" type="translate" values="0 0;5 0;0 0" dur="${dur}s" ${ease} repeatCount="indefinite" additive="sum"/>`
                + `<animateTransform attributeName="transform" type="translate" values="0 0;0 -3;0 0" dur="${(dur * 1.3).toFixed(1)}s" ${ease} repeatCount="indefinite" additive="sum"/>`;
        }
        /* Detail: a second noise at triple frequency, blended in by weight
         * (base cloud + detail overlay, the cloud designer's recipe). */
        const d = +p.detail;
        const detail = d > 0
            ? `<feTurbulence type="${st.type}" baseFrequency="${(fx * 3).toFixed(4)} ${(fy * 3).toFixed(4)}" numOctaves="2" seed="${+p.seed + 3}" stitchTiles="stitch" x="0" y="0" width="50" height="50" result="n2"/><feTile in="n2" result="n2"/>`
              + `<feComposite in="n" in2="n2" operator="arithmetic" k1="0" k2="${(1 - d).toFixed(2)}" k3="${d.toFixed(2)}" k4="0" result="n"/>`
            : '';
        const noise = `<feTurbulence type="${st.type}" baseFrequency="${fx} ${fy}" numOctaves="${p.octaves}" seed="${p.seed}" stitchTiles="stitch" x="0" y="0" width="50" height="50" result="n">${flicker}</feTurbulence>`
            + `<feTile in="n" result="n"/>${detail}`;

        /* One luminance (the red channel) drives colour AND alpha: the
         * three noise channels are independent, mapping them one by one
         * gave muddled colours. */
        const gamma = +p.gamma !== 1 ? rgb(`type="gamma" exponent="${p.gamma}"`) : '';
        const gray = `<feColorMatrix in="n" type="matrix" values="1 0 0 0 0  1 0 0 0 0  1 0 0 0 0  1 0 0 0 0" result="g"/>` + (st.post || '') + gamma;

        const paint = baseUri
            ? `<feImage href="${baseUri}" x="0" y="0" width="50" height="50" result="base"/><feTile in="base" result="base"/>
               <feDisplacementMap in="base" in2="n" scale="${p.scale}" xChannelSelector="R" yChannelSelector="G" result="paint"/>
               <feColorMatrix in="paint" type="hueRotate" values="${p.hue}" result="paint"/>`
            : `<feComponentTransfer in="g" result="paint">
                 <feFuncR type="table" tableValues="${a[0]} ${b[0]}"/>
                 <feFuncG type="table" tableValues="${a[1]} ${b[1]}"/>
                 <feFuncB type="table" tableValues="${a[2]} ${b[2]}"/>
                 <feFuncA type="table" tableValues="${p.alphaLo} ${p.alphaHi}"/>
               </feComponentTransfer>`;
        /* Lighting reads the luminance as a height map: diffuse multiplies
         * the paint (volume, crust), specular adds clipped highlights
         * (wet glints). The light comes from the top-left and turns with
         * the tile's rotation. */
        const sun = '<feDistantLight azimuth="135" elevation="45"/>';
        const diffuse = p.light === 'diffuse' || p.light === 'both'
            ? `<feDiffuseLighting in="g" surfaceScale="${p.relief}" lighting-color="#fff" result="lit">${sun}</feDiffuseLighting>`
              + `<feComposite in="paint" in2="lit" operator="arithmetic" k1="1.4" k2="0" k3="0" k4="0" result="paint"/>`
            : '';
        const specular = p.light === 'specular' || p.light === 'both'
            ? `<feSpecularLighting in="g" surfaceScale="${p.relief}" specularConstant="0.8" specularExponent="20" lighting-color="#fff" result="spec">${sun}</feSpecularLighting>`
              + `<feComposite in="spec" in2="paint" operator="in" result="spec"/>`
              + `<feComposite in="spec" in2="paint" operator="arithmetic" k1="0" k2="1" k3="1" k4="0" result="paint"/>`
            : '';
        const blur = +p.blur > 0 ? `<feGaussianBlur in="paint" stdDeviation="${p.blur}"/>` : '';
        const mask = maskFor(p);
        /* One filtered tile becomes a <pattern>, and the moving rect is
         * filled with it: the pattern engine repeats and resamples it
         * smoothly at any fractional position, where a translated feTile
         * left a seam on every tile edge. The filter still spans three
         * tiles around its rect, so the tile's own edge is computed with
         * its neighbours in view. The mask sits on the outer group, so a
         * puddle keeps its place while its content moves. */
        const params = JSON.stringify(p).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" width="50" height="50" data-composer="${params}">
<defs><filter id="f" x="-1" y="-1" width="3" height="3" color-interpolation-filters="sRGB">${noise}${gray}${paint}${diffuse}${specular}${blur}</filter>
<pattern id="t" patternUnits="userSpaceOnUse" width="50" height="50"><rect width="50" height="50" fill="#000" filter="url(#f)"/></pattern>${mask}</defs>
<g${mask ? ' mask="url(#m)"' : ''} opacity="${p.opacity}"><rect x="-50" y="-50" width="150" height="150" fill="url(#t)">${move}</rect></g>
</svg>`;
    }

    let renderId = 0;
    async function render() {
        const p = read();
        const my = ++renderId;
        SLIDERS.forEach(([key]) => { document.querySelector(`output[for="p-${key}"]`).value = p[key]; });
        const svg = build(p, await baseDataUri(p.base));
        if (my !== renderId) return;
        document.getElementById('preview-inline').innerHTML = svg;
        const uri = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        document.getElementById('preview-inline-big').innerHTML = `<img src="${uri}" width="50" height="50">`.repeat(9);
        document.getElementById('preview-img').src = uri;
        /* A 3x3 patch as the board draws it: the outer tiles fade on the
         * sides with no neighbour, through the board's own edge masks. */
        const EDGE_BITS = [9, 1, 3, 8, 0, 2, 12, 4, 6];
        document.getElementById('preview-edges').innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150" width="150" height="150">${EDGE_DEFS}`
            + EDGE_BITS.map((bits, i) => `<g${bits ? ` mask="url(#elem-edge-${bits})"` : ''}><image href="${uri}" x="${(i % 3) * 50}" y="${Math.floor(i / 3) * 50}" width="50" height="50"/></g>`).join('')
            + '</svg>';
        document.getElementById('svg-out').value = svg;
        document.getElementById('svg-src').textContent = svg;
    }

    const bg = document.getElementById('bg');
    /* The page remembers its controls, ground and target across refreshes. */
    const STORE = 'element-composer';
    const remember = () => { try { localStorage.setItem(STORE, JSON.stringify({p: read(), bg: bg.value, name: document.getElementById('name').value, layer: document.getElementById('layer').value})); } catch (e) {} };
    try {
        const saved = JSON.parse(localStorage.getItem(STORE) || 'null');
        if (saved) {
            apply(saved.p);
            document.getElementById('name').value = saved.name || '';
            document.getElementById('layer').value = saved.layer || 'elements';
            document.getElementById('bg').value = saved.bg || '';
        }
    } catch (e) {}
    ids.forEach(id => el(id).addEventListener('input', () => { render(); remember(); }));
    ['name', 'layer'].forEach(id => document.getElementById(id).addEventListener('input', remember));
    /* Ground behind the previews: a pale or translucent tile means nothing on white. */
    const applyBg = () => document.querySelectorAll('.checker').forEach(box => {
        box.style.background = bg.value ? `url(/${bg.value}) 0 0/50px 50px` : '';
    });
    bg.addEventListener('change', () => { applyBg(); remember(); });
    applyBg();
    document.querySelectorAll('[data-preset]').forEach(btn => btn.addEventListener('click', () => {
        apply(PRESETS[btn.dataset.preset]);
        document.getElementById('name').value = btn.dataset.preset;
        render(); remember();
    }));
    const reload = document.getElementById('reload');
    if (reload) reload.addEventListener('change', () => {
        if (!reload.value) return;
        const [layer, name] = reload.value.split('/');
        apply(COMPOSED[reload.value]);
        document.getElementById('layer').value = layer;
        document.getElementById('name').value = name;
        document.getElementById('replace').checked = true;
        render(); remember();
    });
    render();
})();
</script>

<?php
$content = ob_get_clean();
echo admin_layout('Composeur d\'éléments', $content);
