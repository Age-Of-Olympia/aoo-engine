<?php

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\Service\LockService;

/*
 * Corps de l'écran de contenant (?targetId=), partagé entre la page
 * complète (container.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_container.php). Deux volets : le sac du joueur, le contenu du
 * contenant — un sac EST un contenant, les deux volets lisent la même
 * chose.
 */

$player = PlayerFactory::legacy($_SESSION['playerId']);
$player->get_data();

$containerId = (int) ($_GET['targetId'] ?? 0);
$service = new ContainerService();

$row = (new \Classes\Db())->exe('SELECT name, race, player_type, is_open FROM players WHERE id = ?', [$containerId])->fetch_object();

if ($row === null) {
    exit('error container');
}

$containerName = (string) $row->name !== '' ? (string) $row->name : 'Contenant';

/* The container's face: the same sprite rule as the board and the card. */
$sprite = ((string) $row->player_type === 'item')
    ? \Classes\View::exemplarSprite((string) $row->race, $containerName)
    : \Classes\View::structureSprite((string) $row->race, $containerName);

echo '<h1><img src="' . htmlspecialchars($sprite, ENT_QUOTES, 'UTF-8') . '"'
    . ' style="max-height:48px;vertical-align:middle;margin-right:8px;" alt="" />'
    . htmlspecialchars($containerName, ENT_QUOTES, 'UTF-8') . '</h1>';

/* The lock, to its people: shown even when the container refuses —
 * shut is exactly when the owner needs the button. */
$mayLock = (new LockService())->mayLock($containerId, (int) $player->id);
$isOpen = (bool) $row->is_open;

if ($mayLock) {
    echo '<p><button id="container-lock" data-open="' . ($isOpen ? 0 : 1) . '">'
        . '<span class="ra ra-key"></span> ' . ($isOpen ? 'Fermer' : 'Ouvrir') . '</button></p>';
}

try {
    $service->assertUsable($containerId, (int) $player->id);
} catch (\RuntimeException $e) {
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    if ($mayLock) {
        echo renderContainerScript($containerId);
    }
    return;
}

$bag = $service->contentsOf((int) $player->id);
$held = $service->contentsOf($containerId);

/* Each pane says where it stands: « 3/10 » under a ceiling, a plain
 * count without one — the same rule that refuses the extra line. */
$paneGauge = static function (int $holderId) use ($service): string {
    $capacity = $service->capacityOf($holderId);

    return ' (' . $service->lineCountOf($holderId)
        . ($capacity !== null ? '/' . $capacity : '') . ')';
};

/** The line's 22px face — the peek rule: item art, else the exemplar chain. */
function containerRowSprite(string $itemName): string
{
    $sprite = \Classes\View::exemplarSprite($itemName, $itemName);

    return '<img src="' . htmlspecialchars($sprite, ENT_QUOTES, 'UTF-8')
        . '" style="max-height:22px;vertical-align:middle;margin-right:6px;" alt="" /> ';
}

/**
 * One pane: what a holder has, each line with its face and its move button.
 *
 * @param array{stacks: array<int, array<string, mixed>>, exemplars: array<int, array<string, mixed>>} $contents
 */
function renderContainerPane(string $title, array $contents, string $direction, string $label): void
{
    echo '<div style="min-width:0;">';
    echo '<h2>' . $title . '</h2>';

    if ($contents['stacks'] === [] && $contents['exemplars'] === []) {
        echo '<p><small>Rien.</small></p></div>';
        return;
    }

    echo '<table border="1" class="marbre">';

    foreach ($contents['stacks'] as $row) {
        echo '<tr><td>' . containerRowSprite((string) $row['name'])
            . htmlspecialchars(\App\Service\ContainerService::stackLabel($row), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><button class="container-move" data-kind="stack" data-direction="' . $direction . '"'
            . ' data-item="' . (int) $row['item_id'] . '" data-max="' . (int) $row['n'] . '">' . $label . '</button></td></tr>';
    }

    foreach ($contents['exemplars'] as $row) {
        echo '<tr><td>' . containerRowSprite((string) $row['name'])
            . htmlspecialchars(\App\Service\ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><button class="container-move" data-kind="exemplar" data-direction="' . $direction . '"'
            . ' data-instance="' . (int) $row['instance_id'] . '">' . $label . '</button></td></tr>';
    }

    echo '</table></div>';
}

/* Flex, never wrapping: the two panes stay side by side even in the
 * half-screen panel — the sprites made inline-blocks overflow to a
 * stack. The wrapper scrolls if a screen is truly too narrow. */
echo '<div style="display:flex; flex-wrap:nowrap; gap:0 24px; align-items:flex-start; overflow-x:auto;">';
renderContainerPane('Sac' . $paneGauge((int) $player->id), $bag, 'deposit', 'Déposer →');
renderContainerPane('Coffre' . $paneGauge($containerId), $held, 'withdraw', '← Prendre');
echo '</div>';

/* The chest-side sweep, like « Tout ramasser » on the ground. */
if ($held['stacks'] !== [] || $held['exemplars'] !== []) {
    echo '<p><button id="container-take-all"><span class="ra ra-ammo-bag"></span> Tout prendre</button></p>';
}

echo renderContainerScript($containerId);

/**
 * Fragment script: delegated, namespaced, off() before on() — it
 * re-executes at every panel load.
 */
function renderContainerScript(int $containerId): string
{
    ob_start();
    ?>
    <script>
    (function(){
        var containerId = <?php echo (int) $containerId; ?>;

        function containerCall(payload){
            payload.containerId = containerId;
            aooGestureFetch('api/container/flows.php', payload, function(){
                /* Back to the same panel, like the faction gestures. */
                aooPanelOrReload('load_container.php?targetId=' + containerId, 'Contenant');
            });
        }

        $(document).off('click.containerFlows', '.container-move')
            .on('click.containerFlows', '.container-move', function(){
                var $btn = $(this);
                var action = $btn.data('kind') + '-' + $btn.data('direction');

                if($btn.data('kind') === 'exemplar'){
                    containerCall({ action: action, instanceId: $btn.data('instance') });
                    return;
                }

                var max = parseInt($btn.data('max'), 10);
                /* A single unit has nothing to ask. */
                if(max === 1){
                    containerCall({ action: action, itemId: $btn.data('item'), n: 1 });
                    return;
                }
                aooPrompt('Combien ?', max).then(function(n){
                    if(n == null || n === ''){ return; }
                    n = parseInt(n, 10);
                    if(!(n >= 1) || n > max){ aooAlert('Nombre invalide !'); return; }
                    containerCall({ action: action, itemId: $btn.data('item'), n: n });
                });
            });

        $(document).off('click.containerFlows', '#container-take-all')
            .on('click.containerFlows', '#container-take-all', function(){
                /* The sweep SAYS what it took — a partial one must
                 * explain what stayed behind. */
                aooGestureFetch('api/container/flows.php', { action: 'withdraw-all', containerId: containerId }, function(data){
                    aooResultMessage(data).then(function(){
                        aooPanelOrReload('load_container.php?targetId=' + containerId, 'Contenant');
                    });
                });
            });

        $(document).off('click.containerFlows', '#container-lock')
            .on('click.containerFlows', '#container-lock', function(){
                containerCall({ action: 'lock', open: $(this).data('open') });
            });
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}
