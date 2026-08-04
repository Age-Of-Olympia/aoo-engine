<?php

namespace App\View;

use App\Service\RaceService;

class FactionView
{
    /**
     * @param array<string, mixed>|null $manage the ACTOR's role row
     *        (FactionService::roleOf) — null: plain roster, no gestures.
     *        The flags decide which column cells render; the endpoint
     *        re-checks them server-side either way.
     */
    public static function renderFaction($player,$facJson,$res, ?array $manage = null): void
    {

        $mayKick = !empty($manage['kickMember']);
        $mayEditRole = !empty($manage['editRole']);
        $mayAdd = !empty($manage['addMember']);
        $manages = $mayKick || $mayEditRole;

        echo '
    <table border="1" class="marbre" align="center">
    ';

        echo '
    <tr>
        <th></th>
        <th>Nom</th>
        <th>Peuple</th>
        <th>Xp</th>
        <th>Rang</th>
        <th>Territoire</th>
        ' . ($manages ? '<th>Gestion</th>' : '') . '
    </tr>
    ';

        $raceService = new RaceService();

        while ($row = $res->fetch_object()) {


            $raceJson = $raceService->getRaceData($row->race);

            $planJson = json()->decode('plans', $row->plan);

            if (!$planJson) {

                $planName = '?';
            } else {

                $planName = $planJson->name;
            }


            echo '
        <tr>
            <td>
                <img src="' . $row->avatar . '" />
            </td>
            <td>
                <a href="infos.php?targetId=' . $row->id . '">' . $row->name . '</a>
            </td>
            <td>
                ' . ($raceJson?->name ?? '???') . '
            </td>
            <td>
                ' . $row->xp . '
            </td>
            <td>
                ' . $facJson->role[$row->factionRole]->name . '
            </td>
            <td>
                ';


            // simulate target as a Player()
            $target = (object) array(
                'data' => (object) array(
                    'faction' => $_GET['faction'],
                    'secretFaction' => ""
                )
            );

            if ($player->check_share_factions($target)) {

                echo $planName;
            } else {

                echo '?';
            }

            echo '
            </td>
            ';

            if ($manages) {
                echo '<td>';

                if ($mayEditRole) {
                    echo '<select class="faction-role-select" data-target="' . (int) $row->id . '">';
                    foreach ($facJson->role as $position => $role) {
                        echo '<option value="' . (int) $position . '"'
                            . ((int) $position === (int) $row->factionRole ? ' selected' : '') . '>'
                            . htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') . '</option>';
                    }
                    echo '</select> ';
                }

                if ($mayKick && (int) $row->id !== (int) $player->id) {
                    echo '<button class="faction-kick-btn" data-target="' . (int) $row->id . '"'
                        . ' data-name="' . htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8') . '">Renvoyer</button>';
                }

                echo '</td>';
            }

            echo '
        </tr>
        ';
        }

        echo '
    </table>
    ';

        if ($mayAdd) {
            echo '
    <p>
        <input type="text" id="faction-add-name" placeholder="Nom du personnage" maxlength="100">
        <button id="faction-add-btn">Recruter</button>
        <small>Un personnage sans faction rejoint au rang par défaut.</small>
    </p>
    ';
        }

        if (!empty($manage['initRole'])) {
            self::renderLadder((string) ($_GET['faction'] ?? ''), (int) $manage['position']);
        }

        if ($manages || $mayAdd || !empty($manage['initRole'])) {
            self::renderManageScript((string) ($_GET['faction'] ?? ''));
        }
    }

    /**
     * The ladder editor — the initRole holder's gesture, on ranks strictly
     * below their own: nobody rewrites their own charter. The endpoint
     * re-checks everything.
     */
    private static function renderLadder(string $factionCode, int $actorPosition): void
    {
        $flagLabels = [
            'showPosition' => 'Voir les positions',
            'showForum'    => 'Voir le forum',
            'addMember'    => 'Recruter',
            'kickMember'   => 'Renvoyer',
            'editRole'     => 'Changer les rangs',
            'initRole'     => 'Régler l\'échelle',
        ];

        $editable = array_filter(
            (new \App\Service\FactionService())->rolesOf($factionCode),
            static fn (array $role): bool => (int) $role['position'] < $actorPosition
        );

        if ($editable === []) {
            return;
        }

        echo '
    <h2>Échelle des rangs</h2>
    <table border="1" class="marbre" align="center">
    <tr><th>Rang</th><th>Autorise</th><th></th></tr>
    ';

        foreach ($editable as $role) {
            echo '
        <tr class="faction-ladder-row" data-position="' . (int) $role['position'] . '">
            <td><input type="text" class="faction-ladder-name" maxlength="100"
                 value="' . htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') . '"></td>
            <td>';

            foreach ($flagLabels as $flag => $label) {
                echo '<label style="margin-right: 8px; white-space: nowrap;">'
                    . '<input type="checkbox" class="faction-ladder-flag" data-flag="' . $flag . '"'
                    . (!empty($role[$flag]) ? ' checked' : '') . '> ' . $label . '</label> ';
            }

            echo '</td>
            <td><button class="faction-ladder-save">Enregistrer</button></td>
        </tr>
        ';
        }

        echo '
    </table>
    <p><small>Seuls les rangs sous le vôtre se règlent ici — l\'échelle elle-même (ajout,
    ordre, rang d\'accueil) reste à l\'administration.</small></p>
    ';
    }

    /**
     * The gestures post to api/faction/members.php; the panel reloads on
     * success. Fragment script: delegated, namespaced, off() before on() —
     * it re-executes at every panel load.
     */
    private static function renderManageScript(string $factionCode): void
    {
        ?>
        <script>
        (function(){
            var factionCode = <?php echo json_encode($factionCode); ?>;

            function factionManageCall(payload){
                aooFetch('api/faction/members.php', payload, null)
                    .then(function(data){
                        var message = data && data.result && data.result.message ? data.result.message : '';
                        (message ? aooAlert(message) : Promise.resolve()).then(function(){
                            if(window.hudOpenPanel){
                                window.hudOpenPanel('load_faction.php?faction=' + encodeURIComponent(factionCode), 'Faction');
                            } else {
                                document.location.reload();
                            }
                        });
                    })
                    .catch(autoError());
            }

            $(document).off('click.factionManage', '#faction-add-btn')
                .on('click.factionManage', '#faction-add-btn', function(){
                    var name = ($('#faction-add-name').val() || '').trim();
                    if(!name){ return; }
                    factionManageCall({ action: 'add', name: name });
                });

            $(document).off('click.factionManage', '.faction-kick-btn')
                .on('click.factionManage', '.faction-kick-btn', function(){
                    var $btn = $(this);
                    aooConfirm('Renvoyer ' + $btn.data('name') + ' de la faction ?').then(function(ok){
                        if(!ok){ return; }
                        factionManageCall({ action: 'kick', targetId: $btn.data('target') });
                    });
                });

            $(document).off('change.factionManage', '.faction-role-select')
                .on('change.factionManage', '.faction-role-select', function(){
                    factionManageCall({ action: 'role', targetId: $(this).data('target'), position: parseInt($(this).val(), 10) });
                });

            $(document).off('click.factionManage', '.faction-ladder-save')
                .on('click.factionManage', '.faction-ladder-save', function(){
                    var $row = $(this).closest('.faction-ladder-row');
                    var flags = {};
                    $row.find('.faction-ladder-flag').each(function(){
                        flags[$(this).data('flag')] = $(this).prop('checked') ? 1 : 0;
                    });
                    factionManageCall({
                        action: 'role-def',
                        position: $row.data('position'),
                        name: ($row.find('.faction-ladder-name').val() || '').trim(),
                        flags: flags
                    });
                });
        })();
        </script>
        <?php
    }

    /**
     * The faction's buildings — its assets, shown to its members only (the
     * caller applies that rule, the same one that hides the territory).
     * The playable ones will carry the "take command" gesture (L4b).
     *
     * @param array<int, array<string, mixed>> $buildings FactionService::buildingsOf() rows
     */
    public static function renderBuildings(array $buildings): void
    {
        if ($buildings === []) {
            return;
        }

        echo '
    <h2>Bâtiments</h2>
    <table border="1" class="marbre" align="center">
    <tr>
        <th>Nom</th>
        <th>Type</th>
        <th>État</th>
        <th>Territoire</th>
    </tr>
    ';

        foreach ($buildings as $b) {
            $state = match ($b['build_state']) {
                'construction' => 'En chantier'
                    . ($b['site_total'] !== null ? ' (' . $b['site_done'] . '/' . $b['site_total'] . ')' : ''),
                'ruin' => 'Ruine',
                default => 'Construit',
            };

            $planJson = json()->decode('plans', (string) $b['plan']);

            echo '
        <tr>
            <td><a href="infos.php?targetId=' . (int) $b['id'] . '">' . htmlspecialchars((string) $b['name'], ENT_QUOTES, 'UTF-8') . '</a></td>
            <td>' . htmlspecialchars((string) $b['label'], ENT_QUOTES, 'UTF-8')
                . ($b['playable'] ? ' <span class="ra ra-castle-flag" title="Pilotable par la faction"></span>' : '') . '</td>
            <td>' . $state . '</td>
            <td>' . htmlspecialchars((string) ($planJson->name ?? '?'), ENT_QUOTES, 'UTF-8')
                . ' (' . (int) $b['x'] . ', ' . (int) $b['y'] . ')</td>
        </tr>
        ';
        }

        echo '
    </table>
    ';
    }
}
