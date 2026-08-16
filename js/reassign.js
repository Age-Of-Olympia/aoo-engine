/* Buying a carac rank back at the war school counter.
 *
 * This script ships with a panel fragment and runs again on every open:
 * name the delegate and drop it before binding, otherwise one click
 * fires as many times as the panel has been opened. */
$(document).off('click.reassign').on('click.reassign', '.reassign', function () {

    var $btn = $(this);
    var carac = $btn.data('carac');

    aooConfirm('Réassigner ' + $btn.data('carac-name') + '?').then(function (ok) {

        if (!ok) {

            return;
        }

        $btn.prop('disabled', true);

        /* The school buying back is the one whose panel is open: inside
         * the HUD it is read from the fragment query, not the page URL. */
        aooGestureFetch('api/warschool/reassign.php', {
            carac: carac,
            targetId: aooViewParam('targetId')
        }, function (data) {

            aooResultMessage(data).then(aooReload);
        }).then(function () {

            $btn.prop('disabled', false);
        });
    });
});
