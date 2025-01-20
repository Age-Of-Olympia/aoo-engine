



// copy to clipboard
function copyToClipboard(element) {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(element).text()).select();
    document.execCommand("copy");
    $temp.remove();
    $(element).text('Copié!');
}


// load data on element
function load_data(data, element){

    if(!$(element)[0]){

        alert(data);

        return false;
    }

    $(element).html(data);
}

function aooFetch(url,  payload = null, method = null,autoProcess = true) {
	const headers = { 'Content-Type': 'application/json' }
	const config = {
		method: method?method:payload ? 'POST' : 'GET',
		headers: {
			...headers,
            //todo: add token
		},
	}
	if (payload) {
		config.body = JSON.stringify(payload)
	}

	return window
		.fetch(url, config)
		.then((response) => {
			if (response.ok) {
                if (autoProcess) {
                    return response.json()
                } else {
                    return Promise.resolve(response);
                }
			} else {
				const errorMessage = response.text()
				return Promise.reject(new Error(errorMessage))
			}
		})
}

// preload img
function preload(img, element){


    let $target = element;

    // filler
    $target.animate(

            {opacity:0},

            100,

            function(){

                // Créer un nouvel objet Image
                let mainImage = new Image();

                mainImage.src = img;

                mainImage.onload = function() {

                    $target.attr("src", this.src).animate({opacity:1}, 300);
                };

                // En cas d'erreur de chargement
                mainImage.onerror = function() {

                    alert('error preloading img: '+ img);

                    $target.attr("src", img);
                };
            }
    );
}

$(document).ready(function(){


    // ctrl enter to submit
    $('textarea').keydown( function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.keyCode == 13 || e.keyCode == 10)) {

            $('form').submit();

            $('.submit').click();
        }
    });


    // close card & dialog
    function close_all(){

        if($('#console-wrapper').is(':visible')){
            $('#console-wrapper').hide();
            document.location.reload();
            return false;
        }

        $('#ui-card').hide();
        $('#ui-dialog').hide();
        $('#console-wrapper').hide();
        $('#input-line').val('');
    }


    // special listener for escape key
    document.body.addEventListener('keydown', function(e) {


        if (e.key == "Escape") {
            close_all();
        }

    });

    //bind console keys
    bind_console_keys(document.body);

    // check mail
    const baseTitle = $(document).prop('title');

    var checkMailFunction = function () {

        $.ajax({
            type: "GET",
            url: 'check_mail.php',
            data: {}, // serializes the form's elements.
            success: function(data)
            {
                let trimedData = data.trim();
                if(trimedData != '0'){

                    var $avatar = $('#player-avatar');

                    var $popup = $('<div class="cartouche bulle blink" style="pointer-events: none;">'+ trimedData +'</div>');

                    $avatar.append($popup);

                    // change favicon
                    $("link[rel*='icon']").attr("href", "img/ui/favicons/favicon_alert.png");

                    // change title
                    if(trimedData.length > 0 && trimedData.length < 10){

                        var newTitle = '('+ trimedData +') '+ baseTitle;

                        $(document).prop('title', newTitle);
                    }

                }
            }
        });

        setTimeout(checkMailFunction, 60000);

    }

    if($('#player-avatar')[0] != null){

        setTimeout(checkMailFunction, 1);
    }


    window.addEventListener('wheel', function(event) {
        if (document.body.scrollHeight <= window.innerHeight && event.deltaY !== 0) {
            // Si le contenu du corps ne déborde pas verticalement
            event.preventDefault();
            window.scrollBy(event.deltaY, 0);
        }
    });
});

