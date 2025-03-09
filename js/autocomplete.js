function bindAutocomplete(selectFunction) {

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
};