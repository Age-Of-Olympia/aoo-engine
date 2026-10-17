/* HUD fragments (missives, merchant) land in a page without jQuery UI: load it on first use. */
function loadJQueryUi() {
    if ($.fn.autocomplete) {
        return $.when();
    }
    if (!$('link[href^="css/jquery-ui.min.css"]').length) {
        $('head').append('<link rel="stylesheet" href="css/jquery-ui.min.css" />');
    }
    return $.ajax({url: 'js/jquery-ui.min.js', dataType: 'script', cache: true});
}

function bindAutocomplete(selectFunction) {
    loadJQueryUi().then(function() {

    $("#autocomplete").autocomplete({
        source: function(request, response) {
        $.ajax({
            url: "api/player/get-player-list-by-term.php",
            type: "GET",
            dataType: "json",
            data: {
            data_type:"player_name",
            term: request.term
            },
            success: function(data) {
            response(data);
            }
        });
        },
        minLength: 2,
        select: selectFunction
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
        return $("<li>")
        .append("<div>" + item.label + "</div>")
        .appendTo(ul);
    };

    });
};
