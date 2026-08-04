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
    <h2>Membres</h2>
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
                ' . self::roleTitle($facJson->role[$row->factionRole] ?? null, (int) ($row->factionRoleVariant ?? 0)) . '
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
                    /* Highest rank first, and BOTH names of a rank offered as
                     * if they were two — the member bears the chosen one. */
                    echo '<select class="faction-role-select" data-target="' . (int) $row->id . '">';
                    foreach (array_reverse(array_keys($facJson->role)) as $position) {
                        $role = $facJson->role[$position];
                        $memberVariant = (int) ($row->factionRoleVariant ?? 0);

                        echo '<option value="' . (int) $position . ':0"'
                            . ((int) $position === (int) $row->factionRole && $memberVariant === 0 ? ' selected' : '') . '>'
                            . htmlspecialchars($role->name, ENT_QUOTES, 'UTF-8') . '</option>';

                        if (!empty($role->nameAlt)) {
                            echo '<option value="' . (int) $position . ':1"'
                                . ((int) $position === (int) $row->factionRole && $memberVariant === 1 ? ' selected' : '') . '>'
                                . htmlspecialchars($role->nameAlt, ENT_QUOTES, 'UTF-8') . '</option>';
                        }
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

    /** The title a MEMBER bears: the half their variant chose (Roi or Reine). */
    public static function roleTitle(?object $role, int $variant): string
    {
        if ($role === null) {
            return '?';
        }

        $title = $variant === 1 && !empty($role->nameAlt) ? $role->nameAlt : $role->name;

        return htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    }

    /**
     * The ladder editor — the initRole holder's gesture, on ranks strictly
     * below their own: nobody rewrites their own charter. The SUMMIT alone
     * also rules the structure: landing rank, order, add and remove. The
     * endpoint re-checks everything.
     */
    private static function renderLadder(string $factionCode, int $actorPosition): void
    {
        $flagLabels = [
            'showPosition'  => 'Voir les positions',
            'showForum'     => 'Voir le forum',
            'addMember'     => 'Recruter',
            'kickMember'    => 'Renvoyer',
            'editRole'      => 'Changer les rangs',
            'initRole'      => 'Régler l\'échelle',
            'driveBuilding' => 'Piloter les bâtiments',
            'useChest'      => 'User des coffres',
        ];

        $service = new \App\Service\FactionService();
        $isTop = $actorPosition === $service->topPositionOf($factionCode);

        // One's own rank joins the list: its NAMES rename, its flags freeze.
        // Highest rung first — the ladder reads down from the summit.
        $editable = array_filter(
            $service->rolesOf($factionCode),
            static fn (array $role): bool => (int) $role['position'] <= $actorPosition
        );
        usort($editable, static fn (array $a, array $b): int => $b['position'] <=> $a['position']);

        if ($editable === []) {
            return;
        }

        echo '
    <h2>Échelle des rangs</h2>
    <table border="1" class="marbre" align="center">
    <tr><th>Rang</th><th>Second nom</th><th>Autorise</th><th></th></tr>
    ';

        foreach ($editable as $role) {
            $isOwn = (int) $role['position'] === $actorPosition;

            echo '
        <tr class="faction-ladder-row" data-position="' . (int) $role['position'] . '">
            <td><input type="text" class="faction-ladder-name" maxlength="100"
                 value="' . htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') . '"></td>
            <td><input type="text" class="faction-ladder-name-alt" maxlength="100"
                 placeholder="Roi / Reine — vide : un seul nom"
                 value="' . htmlspecialchars((string) ($role['name_alt'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></td>
            <td>';

            // One's own flags freeze: names rename, power does not self-grant.
            foreach ($flagLabels as $flag => $label) {
                echo '<label style="margin-right: 8px; white-space: nowrap;">'
                    . '<input type="checkbox" class="faction-ladder-flag" data-flag="' . $flag . '"'
                    . (!empty($role[$flag]) ? ' checked' : '') . ($isOwn ? ' disabled' : '') . '> ' . $label . '</label> ';
            }

            echo '</td>
            <td style="white-space: nowrap;"><button class="faction-ladder-save">Enregistrer</button>';

            if ($isTop && !$isOwn) {
                echo ' <button class="faction-ladder-move" data-direction="1" title="Monter d\'un cran">&#8593;</button>'
                    . '<button class="faction-ladder-move" data-direction="-1" title="Descendre d\'un cran">&#8595;</button>'
                    . ' <label style="white-space: nowrap;"><input type="radio" name="faction-ladder-landing" class="faction-ladder-landing"'
                    . (!empty($role['defaultRole']) ? ' checked' : '') . '> Accueil</label>'
                    . ' <button class="faction-ladder-remove" data-name="' . htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8') . '">Retirer</button>';
            }

            echo '</td>
        </tr>
        ';
        }

        echo '
    </table>
    ';

        if ($isTop) {
            echo '
    <p>
        <input type="text" id="faction-ladder-add-name" placeholder="Nom du nouveau rang" maxlength="100">
        <button id="faction-ladder-add">Ajouter un rang</button>
        <small>Il entre juste sous le sommet — les flèches le placent ensuite.
        Un rang ne se retire que vide, et jamais le rang d\'accueil.</small>
    </p>
    ';
        } else {
            echo '
    <p><small>Seuls les rangs sous le vôtre se règlent ici — la structure de l\'échelle
    (ajout, ordre, rang d\'accueil) appartient au rang le plus haut.</small></p>
    ';
        }
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
                    var parts = String($(this).val()).split(':');
                    factionManageCall({
                        action: 'role',
                        targetId: $(this).data('target'),
                        position: parseInt(parts[0], 10),
                        variant: parseInt(parts[1] || '0', 10)
                    });
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
                        nameAlt: ($row.find('.faction-ladder-name-alt').val() || '').trim(),
                        flags: flags
                    });
                });

            $(document).off('click.factionManage', '.faction-ladder-move')
                .on('click.factionManage', '.faction-ladder-move', function(){
                    factionManageCall({
                        action: 'rank-move',
                        position: $(this).closest('.faction-ladder-row').data('position'),
                        direction: $(this).data('direction')
                    });
                });

            $(document).off('change.factionManage', '.faction-ladder-landing')
                .on('change.factionManage', '.faction-ladder-landing', function(){
                    factionManageCall({
                        action: 'rank-landing',
                        position: $(this).closest('.faction-ladder-row').data('position')
                    });
                });

            $(document).off('click.factionManage', '.faction-ladder-remove')
                .on('click.factionManage', '.faction-ladder-remove', function(){
                    var $btn = $(this);
                    aooConfirm('Retirer le rang « ' + $btn.data('name') + ' » ?').then(function(ok){
                        if(!ok){ return; }
                        factionManageCall({
                            action: 'rank-remove',
                            position: $btn.closest('.faction-ladder-row').data('position')
                        });
                    });
                });

            $(document).off('click.factionManage', '#faction-ladder-add')
                .on('click.factionManage', '#faction-ladder-add', function(){
                    var name = ($('#faction-ladder-add-name').val() || '').trim();
                    if(!name){ return; }
                    factionManageCall({ action: 'rank-add', name: name });
                });
        })();
        </script>
        <?php
    }

    /**
     * The faction's buildings — its assets, shown to its members only (the
     * caller applies that rule, the same one that hides the territory).
     * A member sees the "take command" gesture on the playable, finished
     * ones; the server re-checks everything on the way in. Whoever is
     * currently AT a building's commands sees the way back instead.
     *
     * @param array<int, array<string, mixed>> $buildings FactionService::buildingsOf() rows
     */
    public static function renderBuildings(array $buildings, bool $mayDrive = false, int $drivenId = 0): void
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
        <th>État</th>'
        . ($mayDrive ? '
        <th>Contenu</th>
        <th>Commandes</th>' : '') . '
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
            <td>' . $state
                . ($mayDrive ? self::lockCellHtml((int) $b['id'], $drivenId) : '') . '</td>';

            if ($mayDrive) {
                echo '
            <td>' . self::contentsCellHtml((int) $b['id'], $drivenId) . '</td>
            <td>';
                if ((int) $b['id'] === $drivenId) {
                    echo '<button class="faction-drive-release">Reprendre son personnage</button>';
                } elseif ($b['playable'] && $b['build_state'] !== 'ruin' && $b['site_total'] === null) {
                    echo '<button class="faction-drive-take" data-building="' . (int) $b['id'] . '">Prendre les commandes</button>';
                }
                echo '</td>';
            }

            echo '
            <td>' . htmlspecialchars((string) ($planJson->name ?? '?'), ENT_QUOTES, 'UTF-8')
                . ' (' . (int) $b['x'] . ', ' . (int) $b['y'] . ')</td>
        </tr>
        ';
        }

        echo '
    </table>
    ';

        if ($mayDrive) {
            self::renderDriveScript();
        }
    }

    /**
     * The lock, from the panel: Fermer/Ouvrir beside the state, for
     * whoever the rank lets turn it — a remote gesture on purpose, the
     * server re-checks. Nothing for what cannot be shut.
     */
    private static function lockCellHtml(int $entityId, int $actorId): string
    {
        $lock = new \App\Service\LockService();
        $container = new \App\Service\ContainerService();

        if (!$lock->isLockable($entityId)
            || !$lock->mayLock($entityId, $actorId)
            || !$container->mayUse($entityId, $actorId)
        ) {
            return '';
        }

        $isOpen = (bool) \App\Factory\EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchOne('SELECT is_open FROM players WHERE id = ?', [$entityId]);

        return ' <button class="faction-lock-toggle" data-target="' . $entityId . '"'
            . ' data-open="' . ($isOpen ? 0 : 1) . '">'
            . '<span class="ra ra-key"></span> ' . ($isOpen ? 'Fermer' : 'Ouvrir') . '</button>';
    }

    /**
     * What the asset HOLDS, for eyes the rank allows: a short list, or
     * why it stays unseen. Empty for what cannot be shut (no lid, no
     * inside).
     */
    private static function contentsCellHtml(int $entityId, int $actorId): string
    {
        $lock = new \App\Service\LockService();
        if (!$lock->isLockable($entityId)) {
            return '';
        }

        $container = new \App\Service\ContainerService();
        if (!$container->mayUse($entityId, $actorId)) {
            return '—';
        }
        if ($container->closureReasonOf($entityId) !== null) {
            return '<small>(fermé)</small>';
        }

        $contents = $container->contentsOf($entityId);
        $names = array_merge(
            array_map(
                static fn (array $s): string => ucfirst((string) $s['name']) . ' ×' . (int) $s['n'],
                $contents['stacks']
            ),
            array_map(
                static fn (array $e): string => (string) ($e['custom_name'] ?? '') !== ''
                    ? (string) $e['custom_name']
                    : ucfirst((string) $e['name']),
                $contents['exemplars']
            )
        );

        if ($names === []) {
            return '<small>Rien</small>';
        }

        return '<small>' . htmlspecialchars(
            implode(', ', array_slice($names, 0, 5)) . (count($names) > 5 ? '…' : ''),
            ENT_QUOTES,
            'UTF-8'
        ) . '</small>';
    }

    /**
     * Taking or leaving the commands posts to api/faction/drive.php and
     * lands on the map as whoever the session now drives. Fragment
     * script: delegated, namespaced, off() before on() — it re-executes
     * at every panel load.
     */
    private static function renderDriveScript(): void
    {
        ?>
        <script>
        (function(){
            function factionDriveCall(payload){
                aooFetch('api/faction/drive.php', payload, null)
                    .then(function(data){
                        var message = data && data.result && data.result.message ? data.result.message : '';
                        (message ? aooAlert(message) : Promise.resolve()).then(function(){
                            document.location = 'index.php';
                        });
                    })
                    .catch(autoError());
            }

            $(document).off('click.factionDrive', '.faction-drive-take')
                .on('click.factionDrive', '.faction-drive-take', function(){
                    factionDriveCall({ action: 'take', buildingId: $(this).data('building') });
                });

            $(document).off('click.factionDrive', '.faction-drive-release')
                .on('click.factionDrive', '.faction-drive-release', function(){
                    factionDriveCall({ action: 'release' });
                });
        })();
        </script>
        <?php
    }

    /**
     * The faction's chests — its standing containers, shown to its
     * members like the buildings: what each holds (for eyes the rank
     * allows), its lock turnable from HERE, and where it stands.
     *
     * @param array<int, array<string, mixed>> $containers FactionService::containersOf() rows
     */
    public static function renderContainers(array $containers, bool $member = false, int $actorId = 0): void
    {
        if ($containers === []) {
            return;
        }

        echo '
    <h2>Coffres</h2>
    <table border="1" class="marbre" align="center">
    <tr>
        <th>Nom</th>'
        . ($member ? '
        <th>Contenu</th>' : '') . '
        <th>État</th>
        <th>Territoire</th>
    </tr>
    ';

        foreach ($containers as $chest) {
            $planJson = json()->decode('plans', (string) $chest['plan']);

            echo '
        <tr>
            <td><a href="infos.php?targetId=' . (int) $chest['id'] . '">'
                . htmlspecialchars((string) $chest['name'], ENT_QUOTES, 'UTF-8') . '</a></td>'
            . ($member ? '
            <td>' . self::contentsCellHtml((int) $chest['id'], $actorId) . '</td>' : '') . '
            <td>' . ($chest['isOpen'] ? 'Ouvert' : '<span class="ra ra-key"></span> Fermé')
                . ($member ? self::lockCellHtml((int) $chest['id'], $actorId) : '') . '</td>
            <td>' . htmlspecialchars((string) ($planJson->name ?? '?'), ENT_QUOTES, 'UTF-8')
                . ' (' . (int) $chest['x'] . ', ' . (int) $chest['y'] . ')</td>
        </tr>
        ';
        }

        echo '
    </table>
    ';
    }

    /**
     * The lock gestures of the assets tables post to the container
     * endpoint and reopen the panel. Fragment script: delegated,
     * namespaced, off() before on() — it re-executes at every load.
     */
    public static function renderAssetsScript(string $factionCode): void
    {
        ?>
        <script>
        (function(){
            var factionCode = <?php echo json_encode($factionCode); ?>;

            $(document).off('click.factionAssets', '.faction-lock-toggle')
                .on('click.factionAssets', '.faction-lock-toggle', function(){
                    aooFetch('api/container/flows.php', {
                        action: 'lock',
                        containerId: $(this).data('target'),
                        open: $(this).data('open')
                    }, null)
                        .then(function(){
                            if(window.hudOpenPanel){
                                window.hudOpenPanel('load_faction.php?faction=' + encodeURIComponent(factionCode), 'Faction');
                            } else {
                                document.location.reload();
                            }
                        })
                        .catch(autoError());
                });
        })();
        </script>
        <?php
    }
}
