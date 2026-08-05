<?php

use App\Factory\PlayerFactory;
use App\Service\ContainerService;
use App\Service\LockService;
use App\View\ExchangePanesView;

/*
 * Corps de l'écran de contenant (?targetId=), partagé entre la page
 * complète (container.php, enveloppe Ui) et le panneau glissant du HUD
 * (load_container.php). Deux volets : le sac du joueur, le contenu du
 * contenant — un sac EST un contenant, les deux volets lisent la même
 * chose. L'écran lui-même est le motif partagé ExchangePanesView : la
 * banque (BankView) est le même, sur son propre endpoint.
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

ExchangePanesView::openPanes();
ExchangePanesView::pane('Sac' . $paneGauge((int) $player->id), $bag, 'deposit', 'Déposer →');
ExchangePanesView::pane('Coffre' . $paneGauge($containerId), $held, 'withdraw', '← Prendre');
ExchangePanesView::closePanes();

/* The chest-side sweep, like « Tout ramasser » on the ground. */
if ($held['stacks'] !== [] || $held['exemplars'] !== []) {
    echo '<p><button id="container-take-all"><span class="ra ra-ammo-bag"></span> Tout prendre</button></p>';
}

echo ExchangePanesView::script(
    'api/container/flows.php',
    ['containerId' => $containerId],
    'load_container.php?targetId=' . $containerId,
    'Contenant'
);
echo renderContainerScript($containerId);

/**
 * Les gestes PROPRES au contenant — le balai et la serrure ; les
 * transferts ligne à ligne appartiennent au motif partagé
 * (ExchangePanesView::script). Délégué namespacé et purgé.
 */
function renderContainerScript(int $containerId): string
{
    ob_start();
    ?>
    <script>
    (function(){
        var containerId = <?php echo (int) $containerId; ?>;

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
                aooGestureFetch('api/container/flows.php', { action: 'lock', containerId: containerId, open: $(this).data('open') }, function(){
                    aooPanelOrReload('load_container.php?targetId=' + containerId, 'Contenant');
                });
            });
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}
