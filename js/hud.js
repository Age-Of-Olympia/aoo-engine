/*
 * Nouveau HUD (option newHud) — Phase 1.
 * Onglets et chargement des flux du panneau latéral.
 *
 * Stratégie de rafraîchissement volontairement simple : chargement au
 * DOM ready + rechargement au clic d'onglet + bouton manuel. Pas de
 * polling — Log::get est coûteux côté serveur et un déplacement
 * recharge déjà la page entière (js/view.js). À revoir avec le vrai
 * chat (Phase 4).
 */

(function () {

    var FEEDS = {
        mdj: { url: 'load_chat.php', pane: '#hud-feed-mdj' },
        events: { url: 'load_events.php', pane: '#hud-feed-events' }
    };

    function activeTab() {
        return $('.hud-tab--active').data('tab') || 'mdj';
    }

    function loadFeed(name) {
        var feed = FEEDS[name];
        if (!feed) {
            return;
        }
        $.post(feed.url)
            .done(function (data) {
                $(feed.pane).html(data);
            })
            .fail(function () {
                $(feed.pane).html('<p class="hud-feed-empty">Impossible de charger le flux.</p>');
            });
    }

    /*
     * Panneau d'actions : observe.php injecte la carte (#ui-card,
     * .card-actions incluse) dans #ajax-data ; on déplace la
     * .card-actions vers #hud-actions. Déplacer les éléments (et non
     * les cloner) préserve les handlers directs posés par observe.js.
     *
     * Hors de #ui-card, les .action-name deviennent visibles (la règle
     * de main.css ne matche plus) : on force window.visible pour que le
     * premier clic exécute l'action au lieu de "révéler" les noms.
     *
     * Pendant le tutoriel on ne déplace rien : ses surlignages ciblent
     * #ui-card .card-actions dans la carte.
     */
    function relocateCardActions() {
        if (sessionStorage.getItem('tutorial_active') === 'true') {
            return;
        }

        var $actions = $('#ajax-data .card-actions');
        var $panel = $('#hud-actions');

        $panel.empty();

        if ($actions.length) {
            $panel.append($actions);
            window.visible = true;
        }
    }

    $(document).ready(function () {

        loadFeed('mdj');
        loadFeed('events');

        /* childList sans subtree : seul le remplacement du contenu de
         * #ajax-data (view.js .html()) déclenche — ni notre propre
         * déplacement (nœud profond), ni les mises à jour de .card-text
         * après une action. */
        var ajaxData = document.getElementById('ajax-data');
        if (ajaxData) {
            new MutationObserver(relocateCardActions)
                .observe(ajaxData, { childList: true });
            relocateCardActions();
        }

        /* Fermer depuis le panneau : observe.js (handler direct) cache
         * déjà #ui-card ; on vide aussi le panneau d'actions. */
        $('#hud-actions').on('click', '.close-card', function () {
            $('#hud-actions').empty();
        });

        $('.hud-tab').on('click', function () {
            var tab = $(this).data('tab');

            $('.hud-tab').removeClass('hud-tab--active');
            $(this).addClass('hud-tab--active');

            $('#hud-feed-mdj').toggle(tab === 'mdj');
            $('#hud-feed-events').toggle(tab === 'events');

            loadFeed(tab);
        });

        $('#hud-feed-refresh').on('click', function () {
            loadFeed(activeTab());
        });
    });

})();
