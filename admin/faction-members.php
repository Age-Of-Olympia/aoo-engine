<?php
/**
 * Faction members (admin dashboard → Factions → Membres).
 *
 * Assignment surface for what was previously DB-only editing: a character's
 * faction, factionRole, secretFaction and secretFactionRole. One inline form
 * per character, POSTing to faction-members-save.php.
 *
 * Filters: ?faction=<code> (members or secret members), ?faction=__none__
 * (characters without any faction), ?q= name or matricule search.
 *
 * Role selects are repopulated client-side from a code => role-names map
 * emitted by the server, so picking a faction immediately offers its roles.
 * Out-of-range role indexes (after roles were deleted/reordered) are
 * surfaced with a ⚠ option so they can be fixed here.
 *
 * Access enforced by layout.php (alias of factions.php in
 * AdminMenuAccessService).
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Entity\EntityManagerFactory;
use App\Service\CsrfProtectionService;
use App\Service\FactionService;

/** Bornage de la liste : au-delà, affiner par filtre plutôt que paginer. */
const FACTION_MEMBERS_LIMIT = 200;

$service = new FactionService();
$csrfToken = (new CsrfProtectionService())->generateToken();

$factionFilter = trim((string) ($_GET['faction'] ?? ''));
$query = trim((string) ($_GET['q'] ?? ''));

/* ---- Requête personnages ------------------------------------------------ */
$connection = EntityManagerFactory::getEntityManager()->getConnection();

$sql = 'SELECT id, name, race, player_type, faction, factionRole, secretFaction, secretFactionRole
        FROM players';
$where = [];
$params = [];

if ($factionFilter === '__none__') {
    $where[] = "faction = '' AND secretFaction = ''";
} elseif ($factionFilter !== '') {
    $where[] = '(faction = ? OR secretFaction = ?)';
    $params[] = $factionFilter;
    $params[] = $factionFilter;
}
if ($query !== '') {
    if (preg_match('/^-?\d+$/', $query)) {
        $where[] = 'id = ?';
        $params[] = (int) $query;
    } else {
        $where[] = 'name LIKE ?';
        $params[] = '%' . $query . '%';
    }
}

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY name LIMIT ' . (FACTION_MEMBERS_LIMIT + 1);

$characters = ($factionFilter !== '' || $query !== '')
    ? $connection->fetchAllAssociative($sql, $params)
    : [];
$truncated = count($characters) > FACTION_MEMBERS_LIMIT;
$characters = array_slice($characters, 0, FACTION_MEMBERS_LIMIT);

/* ---- Catalogue factions + carte des rôles pour le JS -------------------- */
$factionNames = $service->getFactionNames();
$rolesByFaction = [];
foreach ($service->getAllFactions() as $faction) {
    $rolesByFaction[$faction->getCode()] = $faction->getRoleNames();
}

/** <select> de faction (option vide = sans faction). */
function member_faction_select(string $field, string $current, array $factionNames): string
{
    $options = '<option value="">— aucune —</option>';
    foreach ($factionNames as $code => $name) {
        $options .= '<option value="' . e($code) . '"' . ($current === $code ? ' selected' : '') . '>'
            . e($name) . ' (' . e($code) . ')</option>';
    }
    // Code orphelin (faction supprimée / jamais cataloguée) : proposé quand
    // même, marqué, pour ne pas l'écraser silencieusement en ouvrant la page.
    if ($current !== '' && !isset($factionNames[$current])) {
        $options .= '<option value="' . e($current) . '" selected>⚠ ' . e($current) . ' (inconnue)</option>';
    }

    return '<select class="form-control form-control-sm member-faction" name="' . e($field) . '"'
        . ' data-role-target="' . e($field) . 'Role">' . $options . '</select>';
}

/** <select> de rôle pour la faction courante (index positionnel). */
function member_role_select(string $field, string $factionCode, int $current, array $rolesByFaction): string
{
    $roles = $rolesByFaction[$factionCode] ?? [];
    $options = '';
    foreach ($roles as $position => $roleName) {
        $options .= '<option value="' . $position . '"' . ($current === $position ? ' selected' : '') . '>'
            . $position . ' — ' . e($roleName) . '</option>';
    }
    if ($factionCode !== '' && !isset($roles[$current])) {
        $options .= '<option value="' . $current . '" selected>⚠ ' . $current . ' (hors limites)</option>';
    }
    if ($factionCode === '') {
        $options = '<option value="0" selected>—</option>';
    }

    return '<select class="form-control form-control-sm" name="' . e($field) . '">' . $options . '</select>';
}

/* ---- Rendu --------------------------------------------------------------- */
$filterOptions = '<option value="">Choisir un filtre…</option>'
    . '<option value="__none__"' . ($factionFilter === '__none__' ? ' selected' : '') . '>Sans faction</option>';
foreach ($factionNames as $code => $name) {
    $filterOptions .= '<option value="' . e($code) . '"' . ($factionFilter === $code ? ' selected' : '') . '>'
        . e($name) . ' (' . e($code) . ')</option>';
}

$rows = '';
foreach ($characters as $row) {
    $id = (int) $row['id'];
    $rows .= '<tr><form method="post" action="/admin/faction-members-save.php">'
        . '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="playerId" value="' . $id . '">'
        . '<input type="hidden" name="back" value="' . e('faction=' . urlencode($factionFilter) . '&q=' . urlencode($query)) . '">'
        . '<td><code>' . $id . '</code></td>'
        . '<td><a href="/infos.php?targetId=' . $id . '">' . e($row['name']) . '</a>'
        . ($row['player_type'] !== 'real' ? ' <span class="badge badge-secondary">PNJ</span>' : '') . '</td>'
        . '<td>' . e($row['race']) . '</td>'
        . '<td>' . member_faction_select('faction', (string) $row['faction'], $factionNames) . '</td>'
        . '<td>' . member_role_select('factionRole', (string) $row['faction'], (int) $row['factionRole'], $rolesByFaction) . '</td>'
        . '<td>' . member_faction_select('secretFaction', (string) $row['secretFaction'], $factionNames) . '</td>'
        . '<td>' . member_role_select('secretFactionRole', (string) $row['secretFaction'], (int) $row['secretFactionRole'], $rolesByFaction) . '</td>'
        . '<td><button type="submit" class="btn btn-sm btn-primary">Enregistrer</button></td>'
        . '</form></tr>';
}

$rolesJson = json_encode($rolesByFaction, JSON_UNESCAPED_UNICODE) ?: '{}';

$script = <<<HTML
<script>
(function () {
    /* When a faction select changes, rebuild its sibling role select from
       the code => role-names map (default: role 0). */
    var roles = {$rolesJson};
    document.querySelectorAll('.member-faction').forEach(function (select) {
        select.addEventListener('change', function () {
            var roleSelect = select.closest('tr').querySelector('select[name="' + select.dataset.roleTarget + '"]');
            var list = roles[select.value] || [];
            roleSelect.innerHTML = '';
            if (list.length === 0) {
                roleSelect.appendChild(new Option('—', '0', true, true));
                return;
            }
            list.forEach(function (name, position) {
                roleSelect.appendChild(new Option(position + ' — ' + name, position, position === 0, position === 0));
            });
        });
    });
})();
</script>
HTML;

$content = '<div class="d-flex justify-content-between align-items-center mb-3">'
    . '<h1 class="mb-0">Membres des factions</h1>'
    . '<a class="btn btn-sm btn-outline-secondary" href="/admin/factions.php">← Factions</a></div>'

    . '<form method="get" class="form-inline mb-3" style="gap: 8px;">'
    . '<select class="form-control" name="faction">' . $filterOptions . '</select>'
    . '<input type="text" class="form-control" name="q" value="' . e($query) . '"'
    . ' placeholder="Nom ou matricule…">'
    . '<button type="submit" class="btn btn-outline-primary">Filtrer</button>'
    . '</form>';

if ($factionFilter === '' && $query === '') {
    $content .= '<div class="alert alert-info">Choisissez une faction (ou « Sans faction ») ou cherchez un'
        . ' personnage par nom / matricule pour afficher et modifier ses affectations.</div>';
} elseif ($characters === []) {
    $content .= '<div class="alert alert-warning">Aucun personnage ne correspond à ce filtre.</div>';
} else {
    $content .= ($truncated
            ? '<div class="alert alert-warning py-1">Plus de ' . FACTION_MEMBERS_LIMIT
                . ' résultats — seuls les ' . FACTION_MEMBERS_LIMIT . ' premiers sont affichés, affinez le filtre.</div>'
            : '')
        . '<table class="table table-striped table-sm align-middle"><thead><tr>'
        . '<th>Mat.</th><th>Nom</th><th>Race</th>'
        . '<th>Faction</th><th>Rôle</th>'
        . '<th>Faction secrète</th><th>Rôle secret</th><th></th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<small class="text-muted">Le rôle est un index dans la liste ordonnée des rôles de la faction'
        . ' (players.factionRole). Les entrées ⚠ signalent un code de faction inconnu ou un index hors'
        . ' limites à corriger.</small>';
}

echo admin_layout('Factions — membres', renderFlashMessage() . $content . $script);
