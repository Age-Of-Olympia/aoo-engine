<?php

namespace App\View;

use App\Service\ContainerService;

/**
 * L'écran d'échange à DEUX VOLETS — le motif du coffre, partagé tel
 * quel : un volet par porteur, chaque ligne avec sa vignette et son
 * bouton de transfert, un seul délégué JS qui poste le geste et
 * recharge le panneau.
 *
 * Le coffre (scripts/container/body.php) et la banque (BankView) sont
 * le MÊME écran ; seuls changent l'endpoint des gestes et l'URL de
 * rechargement — c'est tout ce que script() paramètre.
 */
final class ExchangePanesView
{
    /** La vignette 22px d'une ligne — la règle du coup d'œil. */
    public static function rowSprite(string $itemName): string
    {
        $sprite = \Classes\View::exemplarSprite($itemName, $itemName);

        return '<img src="' . htmlspecialchars($sprite, ENT_QUOTES, 'UTF-8')
            . '" style="max-height:22px;vertical-align:middle;margin-right:6px;" alt="" /> ';
    }

    /**
     * Un volet : ce qu'un porteur détient, piles puis exemplaires.
     * $direction vide = volet en LECTURE (consultation à distance) :
     * pas de bouton de transfert.
     *
     * @param array{stacks: array<int, array<string, mixed>>, exemplars: array<int, array<string, mixed>>} $contents
     */
    public static function pane(string $title, array $contents, string $direction, string $label): void
    {
        echo '<div style="min-width:0;">';
        echo '<h2>' . $title . '</h2>';

        if ($contents['stacks'] === [] && $contents['exemplars'] === []) {
            echo '<p><small>Rien.</small></p></div>';
            return;
        }

        echo '<table border="1" class="marbre">';

        foreach ($contents['stacks'] as $row) {
            echo '<tr><td>' . self::rowSprite((string) $row['name'])
                . htmlspecialchars(ContainerService::stackLabel($row), ENT_QUOTES, 'UTF-8') . '</td>';
            if ($direction !== '') {
                echo '<td><button class="pane-move" data-kind="stack" data-direction="' . $direction . '"'
                    . ' data-item="' . (int) $row['item_id'] . '" data-max="' . (int) $row['n'] . '">' . $label . '</button></td>';
            }
            echo '</tr>';
        }

        foreach ($contents['exemplars'] as $row) {
            echo '<tr><td>' . self::rowSprite((string) $row['name'])
                . htmlspecialchars(ContainerService::exemplarEntryLabel($row), ENT_QUOTES, 'UTF-8') . '</td>';
            if ($direction !== '') {
                echo '<td><button class="pane-move" data-kind="exemplar" data-direction="' . $direction . '"'
                    . ' data-instance="' . (int) $row['instance_id'] . '">' . $label . '</button></td>';
            }
            echo '</tr>';
        }

        echo '</table></div>';
    }

    /** L'enveloppe des volets : côte à côte, jamais empilés. */
    public static function openPanes(): void
    {
        echo '<div style="display:flex; flex-wrap:nowrap; gap:0 24px; align-items:flex-start; overflow-x:auto;">';
    }

    public static function closePanes(): void
    {
        echo '</div>';
    }

    /**
     * Le délégué des gestes — namespacé et purgé, le fragment se
     * ré-exécute à chaque panneau. Poste sur $endpoint le geste
     * `<kind>-<direction>` avec $basePayload, puis recharge $refreshUrl.
     *
     * @param array<string, int|string> $basePayload clés jointes à chaque geste
     */
    public static function script(string $endpoint, array $basePayload, string $refreshUrl, string $panelTitle): string
    {
        $endpointJson = json_encode($endpoint);
        $payloadJson = json_encode($basePayload);
        $refreshJson = json_encode($refreshUrl);
        $titleJson = json_encode($panelTitle);

        return <<<HTML
        <script>
        (function(){
            var endpoint = {$endpointJson};
            var basePayload = {$payloadJson};
            var refreshUrl = {$refreshJson};
            var panelTitle = {$titleJson};

            function paneCall(payload){
                Object.assign(payload, basePayload);
                aooGestureFetch(endpoint, payload, function(){
                    /* Retour au même panneau, comme les gestes de faction. */
                    aooPanelOrReload(refreshUrl, panelTitle);
                });
            }

            $(document).off('click.paneFlows', '.pane-move')
                .on('click.paneFlows', '.pane-move', function(){
                    var \$btn = $(this);
                    var action = \$btn.data('kind') + '-' + \$btn.data('direction');

                    if(\$btn.data('kind') === 'exemplar'){
                        paneCall({ action: action, instanceId: \$btn.data('instance') });
                        return;
                    }

                    var max = parseInt(\$btn.data('max'), 10);
                    /* Une unité seule n'a rien à demander. */
                    if(max === 1){
                        paneCall({ action: action, itemId: \$btn.data('item'), n: 1 });
                        return;
                    }
                    aooPrompt('Combien ?', max).then(function(n){
                        if(n == null || n === ''){ return; }
                        n = parseInt(n, 10);
                        if(!(n >= 1) || n > max){ aooAlert('Nombre invalide !'); return; }
                        paneCall({ action: action, itemId: \$btn.data('item'), n: n });
                    });
                });
        })();
        </script>
        HTML;
    }
}
