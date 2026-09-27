<?php

namespace App\View;

use App\Factory\EntityManagerFactory;
use App\Service\ContainerService;
use App\Service\LockService;

/**
 * Tables of standing things one owns — chests, doors, buildings — shared
 * by the faction panel and the inventory: the cells, and the lock,
 * entrust and command gestures.
 *
 * The gestures reload the panel named by the closest `data-reload`
 * ancestor ("url|title"), so each page says where it lives.
 */
final class AssetTableView
{
    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows    cells as HTML
     */
    public static function table(array $headers, array $rows): string
    {
        $html = '<table border="1" class="marbre" align="center"><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        $html .= '</tr>';
        foreach ($rows as $cells) {
            $html .= '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
        }

        return $html . '</table>';
    }

    /** The name opening the entity's sheet — the same as from the board. */
    public static function entityLinkHtml(int $id, string $name): string
    {
        return '<a href="infos.php?targetId=' . $id . '">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /** Plan name and (x, y, z) of an asset. */
    public static function territoryHtml(string $plan, int $x, int $y, int $z): string
    {
        return htmlspecialchars((string) (plans()->read($plan)->name ?? $plan), ENT_QUOTES, 'UTF-8')
            . " ({$x}, {$y}, {$z})";
    }

    /**
     * The lock: Fermer/Ouvrir beside the state, for whoever may turn it
     * — a remote gesture on purpose, the server re-checks. Nothing for
     * what cannot be shut.
     */
    public static function lockCellHtml(int $entityId, int $actorId): string
    {
        if (!(new LockService())->mayTurnLockNow($entityId, $actorId)) {
            return '';
        }

        $isOpen = (bool) EntityManagerFactory::getEntityManager()->getConnection()
            ->fetchOne('SELECT is_open FROM players WHERE id = ?', [$entityId]);

        return ' <button class="asset-lock-toggle" data-target="' . $entityId . '"'
            . ' data-open="' . ($isOpen ? 0 : 1) . '">'
            . '<span class="ra ra-key"></span> ' . ($isOpen ? 'Fermer' : 'Ouvrir') . '</button>';
    }

    /**
     * What the asset HOLDS, for eyes allowed to see: a short list, or
     * why it stays unseen. Empty for what cannot be shut.
     */
    public static function contentsCellHtml(int $entityId, int $actorId): string
    {
        if (!(new LockService())->isLockable($entityId)) {
            return '';
        }

        $container = new ContainerService();
        if (!$container->mayOversee($entityId, $actorId)) {
            return '—';
        }
        if ($container->closureReasonOf($entityId) !== null) {
            return '<small>(fermé)</small>';
        }

        $contents = $container->contentsOf($entityId);
        $names = array_merge(
            array_map([ContainerService::class, 'stackLabel'], $contents['stacks']),
            array_map([ContainerService::class, 'exemplarEntryLabel'], $contents['exemplars'])
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

    public const DOOR_HEADERS = ['Nom', 'État', 'Territoire'];

    /**
     * @param list<array{id: int, name: string, isOpen: bool, x: int, y: int, z: int, plan: string}> $doors
     * @return list<list<string>>
     */
    public static function doorRows(array $doors, int $actorId): array
    {
        $rows = [];
        foreach ($doors as $door) {
            $rows[] = [
                self::entityLinkHtml($door['id'], $door['name']),
                ($door['isOpen'] ? 'Ouverte' : '<span class="ra ra-key"></span> Fermée') . self::lockCellHtml($door['id'], $actorId),
                self::territoryHtml($door['plan'], $door['x'], $door['y'], $door['z']),
            ];
        }

        return $rows;
    }

    /**
     * Buildings: type, state, where; for their people ($withGestures) the
     * lock, the contents and the command gesture on the playable, finished
     * ones — whoever is AT a building's commands sees the way back instead.
     * The server re-checks everything.
     *
     * @param array<int, array<string, mixed>> $buildings FactionService::buildingsOf() / buildingsOwnedBy() rows
     * @param bool $withUpkeep the decay column, which only a faction sees
     */
    public static function buildingsTable(array $buildings, bool $withGestures, int $actorId, bool $withUpkeep): string
    {
        $headers = ['Nom', 'Type', 'État'];
        if ($withUpkeep) {
            $headers[] = 'Entretien';
        }
        if ($withGestures) {
            $headers[] = 'Contenu';
        }
        $headers[] = 'Territoire';
        if ($withGestures) {
            $headers[] = 'Commandes';
        }

        $rows = [];
        foreach ($buildings as $b) {
            $id = (int) $b['id'];
            $state = match ($b['build_state']) {
                'construction' => 'En chantier'
                    . ($b['site_total'] !== null ? ' (' . $b['site_done'] . '/' . $b['site_total'] . ')' : ''),
                'ruin' => 'Ruine',
                default => 'Construit',
            };

            $cells = [
                self::entityLinkHtml($id, (string) $b['name']),
                htmlspecialchars((string) $b['label'], ENT_QUOTES, 'UTF-8')
                    . ($b['playable'] ? ' <span class="ra ra-castle-flag" title="Pilotable"></span>' : ''),
                $state . ($withGestures ? self::lockCellHtml($id, $actorId) : ''),
            ];
            if ($withUpkeep) {
                $cells[] = self::upkeepCellHtml($b);
            }
            if ($withGestures) {
                $cells[] = self::contentsCellHtml($id, $actorId);
            }
            $cells[] = self::territoryHtml((string) $b['plan'], (int) $b['x'], (int) $b['y'], (int) $b['z']);
            if ($withGestures) {
                $cells[] = match (true) {
                    $id === $actorId => '<button class="asset-drive-release">Reprendre son personnage</button>',
                    $b['playable'] && $b['build_state'] !== 'ruin' && $b['site_total'] === null
                        => '<button class="asset-drive-take" data-building="' . $id . '">Prendre les commandes</button>',
                    default => '',
                };
            }
            $rows[] = $cells;
        }

        return self::table($headers, $rows);
    }

    /**
     * How a construction is holding up.
     *
     * The faction page is the ONLY place decay is announced: a construction
     * attached to no faction warns nobody, deliberately — a faction is a
     * group of people, and several pairs of eyes notice a wall going soft.
     * Build alone and you do not know what happens while you are away.
     *
     * Flagged below 75 %, which leaves 25 points before
     * BuildingService::CLOSED_BELOW_PV_PCT shuts the counter: the faction
     * hears about it while the building still works, and while repairing
     * still costs less than rebuilding.
     *
     * @param array<string, mixed> $building a FactionService::buildingsOf() row
     */
    private static function upkeepCellHtml(array $building): string
    {
        if (empty($building['decays'])) {
            return '<span style="opacity: 0.5;">—</span>';
        }

        $pct = (int) $building['life_pct'];

        if ($pct >= \App\Service\Decay\StructureDecayService::ALERT_BELOW_PCT) {
            return $pct . '&nbsp;%';
        }

        $colour = $pct < \App\Service\BuildingService::CLOSED_BELOW_PV_PCT ? 'red' : '#b45f06';

        return '<span style="color: ' . $colour . '; font-weight: bold;" title="'
            . ($pct < \App\Service\BuildingService::CLOSED_BELOW_PV_PCT
                ? 'Trop abîmé pour servir : à réparer.'
                : 'Se dégrade faute d\'entretien — s\'en servir suffit à l\'entretenir.')
            . '">' . $pct . '&nbsp;% <span class="ra ra-bleeding-eye"></span></span>';
    }

    /**
     * Taking or leaving a building's commands posts to api/faction/drive.php
     * (the owner is at home there too) and lands on the map as whoever the
     * session now drives. Fragment script: delegated, namespaced.
     */
    public static function driveScript(): string
    {
        return <<<'HTML'
<script>
(function(){
    function driveCall(payload){
        aooGestureFetch('api/faction/drive.php', payload, function(data){
            aooResultMessage(data).then(function(){
                document.location = 'index.php';
            });
        });
    }

    $(document).off('click.assetDrive', '.asset-drive-take')
        .on('click.assetDrive', '.asset-drive-take', function(){
            driveCall({ action: 'take', buildingId: $(this).data('building') });
        });

    $(document).off('click.assetDrive', '.asset-drive-release')
        .on('click.assetDrive', '.asset-drive-release', function(){
            driveCall({ action: 'release' });
        });
})();
</script>
HTML;
    }

    /** "Confier à ma faction" for the owner of a personal chest. */
    public static function entrustCellHtml(int $chestId): string
    {
        return '<button class="asset-entrust" data-chest="' . $chestId . '">Confier à ma faction</button>';
    }

    /**
     * The gestures of these tables. Fragment script: delegated,
     * namespaced, off() before on() — it re-executes at every load.
     */
    public static function script(): string
    {
        return <<<'HTML'
<script>
(function(){
    function reloadFrom(el){
        var target = ($(el).closest('[data-reload]').data('reload') || '').split('|');
        aooPanelOrReload(target[0], target[1] || '');
    }

    $(document).off('click.assetTable', '.asset-lock-toggle')
        .on('click.assetTable', '.asset-lock-toggle', function(){
            var button = this;
            aooGestureFetch('api/lock/turn.php', { targetId: $(button).data('target'), open: $(button).data('open') }, function(){
                reloadFrom(button);
            });
        });

    $(document).off('click.assetTable', '.asset-entrust')
        .on('click.assetTable', '.asset-entrust', function(){
            var button = this;
            if (!confirm('Confier ce coffre à votre faction ? Il ne sera plus à vous.')) {
                return;
            }
            aooGestureFetch('api/faction/chests.php', { action: 'entrust', chestId: $(button).data('chest') }, function(){
                reloadFrom(button);
            });
        });
})();
</script>
HTML;
    }
}
