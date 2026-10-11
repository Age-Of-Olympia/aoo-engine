$(document).ready(function(e){

    $('.reply').click(function(e){

        /* HUD : le formulaire de réponse s'ouvre dans le panneau
         * (le fil reste dans la pile de retour) ; habillage hérité :
         * pleine page. */
        if(window.hudOpenPanel){

            window.hudOpenPanel('load_forum.php?reply='+ $(this).data('topic'), 'Répondre');
            return;
        }

        document.location = 'forum.php?reply='+ $(this).data('topic');
    });


    $('.post-rewards img:not(.give-reward)').click(function(e){


        $('.post-rewards span:not(.give-reward-span)').html('');

        $(this).next('span').html($(this).attr('title'));
    });

    $('.give-reward').click(function(e){


        var $this = $(this);


        $.ajax({
            type: "POST",
            url: 'forum.php?rewards',
            data: {
                'post': $this.data('post')
            }, // serializes the form's elements.
            success: function(data)
            {
                htmlContent = $('<div>').html(data).find('#data').html();
                // alert(htmlContent);
                $this.next('span').html(htmlContent);
            }
        });
    });

    /* Handler délégué NAMESPACÉ : le script est ré-exécuté à chaque
     * chargement du fil en panneau HUD (load_forum.php) — sans le
     * .off, chaque ouverture empilerait un handler de plus (cookies
     * donnés en double). */
    $(document).off('click.forumCookie').on('click.forumCookie', 'img.give-cookie:not(.disable)', function() {
        var $this = $(this);
    
        if( !$this.hasClass("disable")) {
            let url= 'api/forum/cookie.php';
            let payload = {
            'post-name': $this.data('post-name'),
            'player-id': $this.data('player-id')
            };
            aooFetch(url,payload,null)
            .then(data=>{
                $this.addClass("disable");
                $this.siblings("span").attr('tooltip', data.message);
                $this.siblings("span").text(parseInt($this.siblings("span").text())+1);
            }
            )
            .catch(autoError());      
        }
    });

    $('.forum-moderate').click(function(e){

        e.preventDefault();

        var action = $(this).data('action');

        var confirms = {
            'delete': 'Supprimer ce message ? Les joueurs verront « Message supprimé par la modération ». Restaurable.',
            'hide': 'Masquer ce message ? Il disparaît pour les joueurs, sans trace. Restaurable.'
        };

        if(confirms[action] && !confirm(confirms[action])){
            return;
        }

        forumModerate({action: action, name: String($(this).data('post'))});
    });

    $('.forum-rename').click(function(e){

        e.preventDefault();

        var title = prompt('Nouveau titre du sujet :', $(this).data('title'));

        if(!title || !title.trim()) return;

        forumModerate({action: 'rename', name: String($(this).data('topic')), title: title.trim()});
    });
});

function forumModerate(payload){

    aooFetch('api/forum/moderate.php', payload).then(function(data){

        if(data.error){
            alert(data.error);
            return;
        }

        if(window.hudOpenPanel){
            window.hudOpenPanel('load_forum.php?topic='+ window.topicName +'&page='+ window.pageN, 'Missives');
            return;
        }

        document.location.reload();
    });
}
