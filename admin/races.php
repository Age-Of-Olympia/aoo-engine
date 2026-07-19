<?php
/**
 * Race management (admin dashboard → Races).
 *
 * Two views, routed on ?action:
 *   - list (default): every race with flags, colors and list sizes.
 *   - edit / new    : one form for everything a race defines — identity
 *                     (label, description), flags (playable / hidden), colors,
 *                     faction / plan / animateur, the 16 CARACS stats, and the
 *                     two name lists (starter actions, spells).
 *
 * Races were migrated from datas/*\/races/*.json to the DB
 * (Version20260710120000_RacesFromJson); this page is the editing surface
 * that replaces hand-editing those files. Delete is guarded: refused while
 * any character still has players.race = name (retire a race in use by
 * unchecking "jouable" + checking "cachée" instead).
 *
 * All mutations POST to races-save.php (CSRF-validated, PRG). This page only
 * renders. Access enforced by layout.php via AdminMenuAccessService.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\Race;
use App\Service\ActionService;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;
use App\Service\RaceService;

function race_flag_badge(bool $on, string $labelOn, string $labelOff): string
{
    return $on
        ? '<span class="badge badge-success">' . e($labelOn) . '</span>'
        : '<span class="badge badge-secondary">' . e($labelOff) . '</span>';
}

/**
 * Cellule « Personnages » : joueurs réels (avec le sous-compte inactifs,
 * seuil INACTIVE_TIME) et PNJ, distingués — un total brut mélangerait des
 * populations que l'admin gère différemment.
 *
 * @param array{players: int, inactive: int, npcs: int} $counts
 */
function race_character_counts(array $counts): string
{
    if ($counts['players'] === 0 && $counts['npcs'] === 0) {
        return '<span class="text-muted">—</span>';
    }

    $parts = [];
    if ($counts['players'] > 0) {
        $parts[] = '<strong>' . $counts['players'] . ' joueur' . ($counts['players'] > 1 ? 's' : '') . '</strong>'
            . ($counts['inactive'] > 0
                ? ' <span class="text-muted">(dont ' . $counts['inactive'] . ' inactif'
                    . ($counts['inactive'] > 1 ? 's' : '') . ')</span>'
                : '');
    }
    if ($counts['npcs'] > 0) {
        $parts[] = $counts['npcs'] . ' PNJ';
    }

    return implode(' · ', $parts);
}

/**
 * @param Race[] $races
 */
/**
 * Éléments de carte utilisables comme saignement : image dans
 * img/elements + entrée au catalogue des effets (exigence d'Element::put).
 *
 * @return array<string, string>
 */
function bleed_options(): array
{
    $effectService = new \App\Service\EffectService();

    $out = [];
    foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/img/elements/*.{png,webp,gif}', GLOB_BRACE) ?: [] as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if ($effectService->exists($name)) {
            $out[$name] = $name;
        }
    }
    ksort($out);
    return $out;
}

/** Sortes d'entités — valeurs de la source unique EntityCategory. */
function kind_select(bool $isStructure): string
{
    $labels = \App\Enum\EntityCategory::options();
    $labels[\App\Enum\EntityCategory::Structure->value] .= ' (bâtiment, objet unique)';

    return formSelect(
        'kind',
        $labels,
        $isStructure ? \App\Enum\EntityCategory::Structure->value : \App\Enum\EntityCategory::Character->value,
        null,
        'class="form-control form-control-sm d-inline-block" style="width:auto"'
    );
}

/**
 * Même table races, deux VISAGES d'admin (décision du 2026-07-19) : les
 * RACES (personnages) et les TYPES DE BÂTIMENTS (sorte structure) — deux
 * sections séparées pour ne plus mélanger peuples et murs.
 *
 * @param Race[] $races déjà filtrées par sorte
 */
function race_render_list(array $races, bool $structureMode = false): string
{
    $selfPage = $structureMode ? '/admin/structure-types.php' : '/admin/races.php';

    if ($structureMode) {
        // « Posés » : entités de ce type actuellement dans le monde.
        $placedByType = [];
        $res = (new \Classes\Db())->exe(
            "SELECT race, COUNT(*) AS n FROM players WHERE player_type IN ('building', 'unique') GROUP BY race"
        );
        while ($row = $res->fetch_object()) {
            $placedByType[(string) $row->race] = (int) $row->n;
        }
    } else {
        $charactersByRace = (new RaceService())->countCharactersByRaceName();
    }

    $rows = '';
    foreach ($races as $race) {
        $rows .= '<tr>'
            . '<td><code>' . e($race->getName()) . '</code></td>'
            . '<td>' . e($race->getLabel()) . '</td>';

        if ($structureMode) {
            $rows .= '<td>' . ($race->getStructureNature() === 'obstacle'
                    ? '<span class="badge badge-secondary">Obstacle</span>'
                    : '<span class="badge badge-info">Édifice</span>') . ' '
                . ($race->blocksPassage() ? '' : '<span class="badge badge-light" title="On marche sur sa case">passable</span> ')
                . ($race->blocksProjectiles() ? '' : '<span class="badge badge-light" title="Les tirs passent au-dessus">tirs libres</span>')
                . '</td>';
        } else {
            $rows .= '<td>' . race_flag_badge($race->getPlayable(), 'Jouable', 'Non') . ' '
                . race_flag_badge(!$race->getHidden(), 'Visible', 'Cachée') . '</td>';
        }

        $rows .= '<td><span style="display:inline-block;width:1.2em;height:1.2em;vertical-align:middle;'
            . 'border:1px solid #999;background:' . e($race->getBgColor()) . '"></span> '
            . e($race->getBgColor()) . '</td>';

        if ($structureMode) {
            $rows .= '<td>' . (int) $race->getCarac('pv') . ' PV</td>'
                . '<td>' . (($placedByType[$race->getName()] ?? 0) > 0
                    ? '<strong>' . $placedByType[$race->getName()] . '</strong>'
                    : '<span class="text-muted">—</span>') . '</td>';
        } else {
            $characters = $charactersByRace[$race->getName()] ?? ['players' => 0, 'inactive' => 0, 'npcs' => 0];
            $rows .= '<td>' . e($race->getFaction()) . '</td>'
                . '<td>' . (int) $race->getCarac('pv') . ' PV / ' . (int) $race->getCarac('mvt') . ' Mvt / '
                . (int) $race->getCarac('a') . ' A</td>'
                . '<td>' . count($race->getStarterActionNames()) . ' actions, '
                . count($race->getSpellNames()) . ' sorts</td>'
                . '<td>' . race_character_counts($characters) . '</td>';
        }

        $rows .= '<td><a class="btn btn-sm btn-outline-primary" href="' . $selfPage . '?action=edit&amp;name='
            . e(urlencode($race->getName())) . '">Éditer</a> '
            . '<a class="btn btn-sm btn-outline-secondary" title="Exporter (bundle JSON)"'
            . ' href="/admin/action-export.php?type=race&amp;id=' . (int) $race->getId() . '">JSON</a></td>'
            . '</tr>';
    }

    $headers = $structureMode
        ? '<th>Code</th><th>Nom</th><th>Nature</th><th>Couleur</th><th>PV</th>'
            . '<th title="Entités de ce type posées dans le monde">Posés</th><th></th>'
        : '<th>Code</th><th>Nom</th><th>Statut</th><th>Couleur</th><th>Faction</th>'
            . '<th>Stats clés</th><th>Listes</th><th title="Personnages (joueurs et PNJ) utilisant cette race">Personnages</th><th></th>';

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . ($structureMode ? 'Types de bâtiments' : 'Races') . '</h1>'
        . '<div class="d-flex gap-2">'
        . '<a class="btn btn-outline-secondary" href="/admin/action-export.php?type=race"'
        . ' title="Télécharger toutes les races et types en bundle JSON (famille commune race)">'
        . '<i class="fas fa-download"></i> Exporter (JSON)</a>'
        . '<a class="btn btn-outline-secondary" href="/admin/action-import.php"'
        . ' title="Importer un bundle JSON (avec prévisualisation avant application)">'
        . '<i class="fas fa-upload"></i> Importer</a>'
        . '<a class="btn btn-primary" href="' . $selfPage . '?action=new">'
        . ($structureMode ? '+ Nouveau type' : '+ Nouvelle race') . '</a>'
        . '</div></div>'
        . '<table class="table table-striped table-sm" data-admin-list data-page-size="30"><thead><tr>'
        . $headers
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

/**
 * <select> de la faction de départ, depuis le catalogue (admin/factions.php).
 * Une valeur hors catalogue (faction supprimée) reste proposée, marquée ⚠
 * par la sentinelle générique de renderSelectOptions.
 */
function race_faction_select(string $current): string
{
    $options = [];
    foreach ((new FactionService())->getFactionNames() as $code => $name) {
        $options[$code] = $name . ' (' . $code . ')';
    }

    return formSelect('faction', $options, $current !== '' ? $current : null, '— aucune —');
}

function race_render_form(?Race $race, string $csrfToken, bool $structureMode = false): string
{
    $isEdit = $race !== null;
    $action = $isEdit ? 'update' : 'create';
    $noun = $structureMode ? 'Type de bâtiment' : 'Race';
    $title = $isEdit
        ? $noun . ' : ' . e($race->getLabel()) . ' <span class="text-muted">(' . e($race->getName()) . ')</span>'
        : 'Nouveau ' . strtolower($noun);

    $nameField = $isEdit
        ? '<input type="hidden" name="name" value="' . e($race->getName()) . '">'
            . '<input type="text" class="form-control" value="' . e($race->getName()) . '" disabled>'
            . '<small class="form-text text-muted">Le code est référencé par players.race — non modifiable.</small>'
        : '<input type="text" class="form-control" name="name" required pattern="[a-z][a-z0-9_]*"'
            . ' placeholder="ex: centaure">'
            . '<small class="form-text text-muted">Minuscules / chiffres / _ — stocké dans players.race.</small>';

    $caracInputs = '';
    foreach (CARACS as $key => $short) {
        $label = CARACS_TXT[$key] ?? $short;
        $value = $isEdit ? $race->getCarac($key) : 0;

        // Un type de bâtiment ne vit que par ses PV : les autres caracs
        // restent postées (le save les exige toutes) mais invisibles.
        if ($structureMode && $key !== 'pv') {
            $caracInputs .= '<input type="hidden" name="carac[' . e($key) . ']" value="' . (int) $value . '">';
            continue;
        }

        $caracInputs .= '<div class="form-group col-md-3 col-6">'
            . '<label title="' . e($label) . '">' . e($short) . '</label>'
            . '<input type="number" class="form-control" name="carac[' . e($key) . ']"'
            . ' value="' . (int) $value . '" required>'
            . '</div>';
    }

    $starterActions = $isEdit ? implode("\n", $race->getStarterActionNames()) : '';
    $spells = $isEdit ? implode("\n", $race->getSpellNames()) : '';

    // Autocomplete catalog for both list editors: every action name the game
    // knows (configured actions labelled by type + legacy granted-only names).
    $knownActions = (new ActionService())->getKnownActionNames();
    $catalog = renderDatalist('race-action-catalog', $knownActions);

    // Search + add picker wired onto a textarea by data-target.
    $picker = static fn (string $target): string =>
        '<div class="input-group input-group-sm mb-2">'
        . '<input type="text" class="form-control race-action-picker" list="race-action-catalog"'
        . ' data-target="' . e($target) . '" placeholder="Rechercher une action à ajouter…" autocomplete="off">'
        . '<div class="input-group-append">'
        . '<button type="button" class="btn btn-outline-primary race-action-add">Ajouter</button>'
        . '</div></div>';

    $pickerScript = <<<HTML
<script>
(function () {
    /* Append the picked action name to the target textarea (one per line,
       no duplicates), on button click or Enter in the picker field. */
    function add(picker) {
        var name = picker.value.trim();
        if (name === '') return;
        var area = document.querySelector('textarea[name="' + picker.dataset.target + '"]');
        var lines = area.value.split(/\\n/).map(function (l) { return l.trim(); }).filter(Boolean);
        if (lines.indexOf(name) === -1) {
            lines.push(name);
            area.value = lines.join('\\n');
        }
        picker.value = '';
        picker.focus();
    }
    document.querySelectorAll('.race-action-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
            add(btn.closest('.input-group').querySelector('.race-action-picker'));
        });
    });
    document.querySelectorAll('.race-action-picker').forEach(function (picker) {
        picker.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); add(picker); }
        });
    });
})();
</script>
HTML;

    return '<div class="d-flex justify-content-between align-items-center mb-3">'
        . '<h1 class="mb-0">' . $title . '</h1>'
        . '<a class="btn btn-sm btn-outline-secondary" href="'
        . ($structureMode ? '/admin/structure-types.php' : '/admin/races.php')
        . '">← Retour à la liste</a></div>'

        . '<form method="post" action="/admin/races-save.php?action=' . $action . '">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'

        . '<div class="card mb-3"><div class="card-header">Identité</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-4"><label>Code</label>' . $nameField . '</div>'
        . '<div class="form-group col-md-4"><label>Nom affiché</label>'
        . '<input type="text" class="form-control" name="label" required value="'
        . e($isEdit ? $race->getLabel() : '') . '"></div>'
        . '<div class="form-group col-md-4"><label>Flags</label><div>'
        . ($structureMode
            ? '<input type="hidden" name="kind" value="structure">'
            : '<label class="mr-3">Sorte ' . kind_select($isEdit && $race->isStructureKind()) . '</label> ')
        . '<label class="mr-3">Saignement '
        . formSelect(
            'bleeds',
            bleed_options(),
            $isEdit && $race->getBleeds() !== '' ? $race->getBleeds() : null,
            '— rien —',
            'class="form-control form-control-sm d-inline-block" style="width:auto"'
            . ' title="Élément versé au sol quand l\'entité est blessée — rien : un mur ne saigne pas."'
        )
        . '</label> '
        . '<label class="mr-3">Nature '
        . formSelect(
            'structure_nature',
            ['edifice' => 'Édifice (porte)', 'obstacle' => 'Obstacle (mur)'],
            $isEdit && $race->getStructureNature() === 'obstacle' ? 'obstacle' : 'edifice',
            null,
            'class="form-control form-control-sm d-inline-block" style="width:auto"'
            . ' title="Structures seulement — Édifice : vrai bâtiment, a une porte (Ouvert/Fermé, dialogue). Obstacle : mur construit, sans porte (is_open = future passabilité)."'
        )
        . '</label> '
        . '<label class="mr-3"><input type="checkbox" name="blocks_passage" '
        . checked(!$isEdit || $race->blocksPassage())
        . ' title="Structures seulement — décoché : on marche sur sa case (mobilier bas, passage)."> Bloque le passage</label> '
        . '<label class="mr-3"><input type="checkbox" name="blocks_projectiles" '
        . checked(!$isEdit || $race->blocksProjectiles())
        . ' title="Structures seulement — décoché : les tirs passent au-dessus (table, muret bas…)."> Bloque les tirs</label> '
        . ($structureMode ? '' : '<label class="mr-3"><input type="checkbox" name="playable" '
            . checked($isEdit && $race->getPlayable()) . '> Jouable (proposée à l\'inscription)</label>')
        . '<label><input type="checkbox" name="hidden" '
        . checked($isEdit && $race->getHidden()) . '> Cachée</label>'
        . '<small class="form-text text-muted">Cachée : les personnages de cette race ne définissent pas'
        . ' le « premier joueur » qui sert de référence au bonus d\'XP de rattrapage'
        . ' (un perso admin très haut niveau ne doit pas gonfler le bonus de tout le serveur).</small>'
        . '</div></div>'
        . '<div class="form-group col-12"><label>Description</label>'
        . '<textarea class="form-control" name="description" rows="5">'
        . e($isEdit ? $race->getDescription() : '') . '</textarea></div>'
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Apparence &amp; monde</div><div class="card-body"><div class="row">'
        . '<div class="form-group col-md-2"><label>Couleur de fond</label>'
        . '<input type="color" class="form-control" name="bgColor" value="'
        . e($isEdit ? $race->getBgColor() : '#FFFFFF') . '">'
        . '<small class="form-text text-muted">Carte, classements, bordures raceHint.</small></div>'
        . '<div class="form-group col-md-2"><label>Couleur du texte</label>'
        . '<input type="text" class="form-control" name="color" value="'
        . e($isEdit ? $race->getColor() : 'black') . '"></div>'
        . '<div class="form-group col-md-2"><label>Couleur de blessure</label>'
        . '<input type="color" class="form-control" name="wound_color" value="'
        . e($isEdit ? $race->getWoundColor() : \App\Service\RaceService::DEFAULT_WOUND_COLOR) . '">'
        . '<small class="form-text text-muted">Voile des PV perdus (portrait, carte) — rouge sang par défaut, bronze pour une structure par exemple.</small></div>'
        . ($structureMode
            ? '<input type="hidden" name="faction" value="' . e($isEdit ? $race->getFaction() : '') . '">'
                . '<input type="hidden" name="plan" value="' . e($isEdit ? $race->getPlan() : '') . '">'
                . '<input type="hidden" name="animateurId" value="' . e($isEdit ? (string) $race->getAnimateurId() : '') . '">'
            : '<div class="form-group col-md-3"><label>Faction de départ</label>'
                . race_faction_select($isEdit ? $race->getFaction() : '')
                . '<small class="form-text text-muted">Copiée dans players.faction à la création du personnage.'
                . ' Plusieurs races peuvent partager une faction.</small></div>'
                . '<div class="form-group col-md-3"><label>Plan d\'origine</label>'
                . '<input type="text" class="form-control" name="plan" value="'
                . e($isEdit ? $race->getPlan() : '') . '"></div>'
                . '<div class="form-group col-md-2"><label>Animateur (id joueur)</label>'
                . '<input type="number" class="form-control" name="animateurId" value="'
                . e($isEdit ? (string) $race->getAnimateurId() : '') . '">'
                . '<small class="form-text text-muted">Vide = aucun. Id négatif = PNJ.</small></div>')
        . '</div></div></div>'

        . '<div class="card mb-3"><div class="card-header">Caractéristiques</div><div class="card-body"><div class="row">'
        . $caracInputs
        . '</div></div></div>'

        . ($structureMode
            ? '<textarea name="starter_actions" hidden>' . e($starterActions) . '</textarea>'
                . '<textarea name="spells" hidden>' . e($spells) . '</textarea>'
            : '<div class="card mb-3"><div class="card-header">Listes d\'actions</div><div class="card-body"><div class="row">'
                . '<div class="form-group col-md-6"><label>Actions de départ (une par ligne)</label>'
                . $picker('starter_actions')
                . '<textarea class="form-control" name="starter_actions" rows="10" spellcheck="false">'
                . e($starterActions) . '</textarea>'
                . '<small class="form-text text-muted">Accordées à la création du personnage (ex: attaquer, dmg1/pic_de_pierre).</small></div>'
                . '<div class="form-group col-md-6"><label>Sorts apprenables (un par ligne)</label>'
                . $picker('spells')
                . '<textarea class="form-control" name="spells" rows="10" spellcheck="false">'
                . e($spells) . '</textarea>'
                . '<small class="form-text text-muted">Conditionnent le marchand de sorts et les objets à sort intégré.</small></div>'
                . '</div></div></div>')

        . '<button type="submit" class="btn btn-primary">'
        . ($isEdit ? 'Enregistrer' : ($structureMode ? 'Créer le type' : 'Créer la race')) . '</button>'
        . '</form>'
        . ($isEdit ? race_render_delete_zone($race, $csrfToken) : '')
        . $catalog
        . $pickerScript;
}

/**
 * Zone de suppression du formulaire d'édition. Le garde-fou côté serveur
 * (RaceService::deleteRace) refuse tant que players.race référence la race ;
 * ici on adapte juste l'UI : bouton actif + confirmation, ou explication.
 */
function race_render_delete_zone(Race $race, string $csrfToken): string
{
    $service = new RaceService();
    $players = $service->countPlayersUsingRace($race->getName());
    $counts = $service->countCharactersByRaceName()[$race->getName()]
        ?? ['players' => 0, 'inactive' => 0, 'npcs' => 0];

    $body = $players > 0
        ? '<p class="mb-0 text-muted">Suppression impossible : cette race est encore utilisée par '
            . race_character_counts($counts) . '. Pour la retirer du jeu, décochez'
            . ' « Jouable » et cochez « Cachée ».</p>'
        : '<form method="post" action="/admin/races-save.php?action=delete" class="d-flex align-items-center gap-3"'
            . ' onsubmit="return confirm(\'Supprimer définitivement la race « '
            . e($race->getName()) . ' » et ses listes d\\\'actions/sorts ?\');">'
            . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="name" value="' . e($race->getName()) . '">'
            . '<button type="submit" class="btn btn-outline-danger">Supprimer la race</button>'
            . '<small class="text-muted">Aucun personnage n\'utilise cette race. Supprime aussi ses listes'
            . ' d\'actions et de sorts — pensez à exporter un bundle JSON avant, pour pouvoir la restaurer.</small>'
            . '</form>';

    return '<div class="card mt-4 border-danger"><div class="card-header text-danger">Zone dangereuse</div>'
        . '<div class="card-body">' . $body . '</div></div>';
}

/* -------------------------------------------------------------------------
 * Route
 * ---------------------------------------------------------------------- */
$csrfToken = (new CsrfProtectionService())->generateToken();
$service = new RaceService();

$action = $_GET['action'] ?? 'list';

// Deux sections pour une même table (décision du 2026-07-19) : Races
// (personnages) ici, Types de bâtiments via admin/structure-types.php
// (wrapper qui pose ?kind=structure avant d'inclure cette page).
$structureMode = (($_GET['kind'] ?? '') === 'structure');

if ($action === 'new') {
    $content = race_render_form(null, $csrfToken, $structureMode);
} elseif ($action === 'edit') {
    $race = $service->getRaceByName((string) ($_GET['name'] ?? ''));
    if ($race === null) {
        setFlash('warning', 'Race introuvable.');
        redirectTo($structureMode ? '/admin/structure-types.php' : '/admin/races.php');
    }
    // La sorte de la ligne fait foi : éditer une structure depuis
    // n'importe où montre le visage « type de bâtiment ».
    $content = race_render_form($race, $csrfToken, $structureMode || $race->isStructureKind());
} else {
    $kept = array_values(array_filter(
        $service->getAllRaces(),
        static fn (Race $race): bool => $race->isStructureKind() === $structureMode
    ));
    $content = race_render_list($kept, $structureMode);
}

echo admin_layout($structureMode ? 'Types de bâtiments' : 'Races', renderFlashMessage() . $content);
