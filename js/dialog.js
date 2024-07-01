$(document).ready(function(){


    // preload avatar
    var $avatarImg = $(".dialog-template-img img");

    preload($avatarImg.data("img"), $avatarImg);


    // show first node
    $(".dialog-node").first().fadeIn();


    // option click
    $(".node-option").click(function(e){


        if($(this).data('url')){

            document.location = $(this).data('url');

            return false;
        }


        if($(this).data('set-name')){

            window[$(this).data('set-name')] = $(this).data('set-val');
        }


        var go = $(this).data("go");


        // exit
        if(go == "EXIT"){

            $('#ui-dialog').hide();
        }

        // RESET
        else if(go == "RESET"){


            // reset img
            $(".dialog-creature-avatar img").hide();
            $(".dialog-template-img").fadeIn();

            // go first node
            go = $(".dialog-node").first().data("node");
        }


        // if hidden, show dialog box
        $(".dialog-template-box").show();


        // hide all nodes
        $(".dialog-node").hide();


        // next
        var $next = $("#node"+ go);


        // avatar
        if($next.data("avatar")){


            $creatureImg = $(".dialog-creature-avatar img");

            // creatureImg is different from next avatar
            if(!$creatureImg.attr("src").endsWith($next.data("avatar"))){


                // hide player img
                $(".dialog-template-img").hide();

                preload($next.data("avatar"), $creatureImg);
            }
        }


        // next node exists
        if($next.length){

            // show go node
            $next.show();

            // dialog large show : hide dialog box
            if($("#node"+ go).hasClass("dialog-large")){

                $(".dialog-template-box").hide();
            }
        }

        // error node
        else{


            $("#nodeerror").show();
        }
    });
});
