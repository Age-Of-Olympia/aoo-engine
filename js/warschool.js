/* Achat de sorts / techniques / passifs à l'école de guerre.
 * Partagé par les six vues (Mêlée, Distance, Magie, Sorts,
 * Furtivité, Survie) : le même script était copié-collé dans
 * chacune, avec dérive (la vue Sorts avait perdu `var type`).
 *
 * Dans le HUD, la vue est un fragment (load_warschool.php) injecté
 * dans un panneau : window.location.href pointe sur index.php, qui
 * ignore le POST d'achat et renvoie la page entière. On capture la
 * requête du panneau au moment où le fragment s'exécute
 * (window.hudPanelQuery, posée par js/hud.js juste avant
 * l'injection) pour poster sur le bon endpoint ; dans l'habillage
 * hérité, l'URL de la page reste la bonne cible. */
$(function () {

    var buyUrl = window.hudPanelQuery
        ? 'load_warschool.php?' + window.hudPanelQuery
        : window.location.href;

    $('.buy-skill-btn').click(function () {

        var btn = $(this);
        var skillId = btn.data('id');
        var type = btn.data('type'); /* 'active' ou 'passive' */

        aooConfirm('Voulez-vous vraiment apprendre cette compétence ?').then(function (ok) {

            if (!ok) return;

            btn.prop('disabled', true);

            var postData = (type === 'passive')
                ? { buyPassiveId: skillId }
                : { buySkillId: skillId };

            $.ajax({
                type: 'POST',
                url: buyUrl,
                data: postData,
                success: function (response) {

                    /* On cherche la div #data dans la réponse brute */
                    var message = $(response).find('#data').html() || $(response).filter('#data').html();

                    if (!message) {
                        message = 'Réponse serveur : ' + response.replace(/<[^>]*>?/gm, '');
                    }

                    /* Message PUIS rechargement : l'alerte modale n'est pas bloquante */
                    aooAlert($('<div>').html(message).text()).then(function () {
                        aooReload();
                    });
                },
                error: function (xhr) {
                    aooAlert('Erreur réseau : ' + xhr.status);
                    btn.prop('disabled', false);
                }
            });
        });
    });
});
