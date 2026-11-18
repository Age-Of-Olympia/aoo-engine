function upload_file(e) {
    e.preventDefault();
    ajax_file_upload(e.dataTransfer.files[0]);
}

function file_explorer() {
    document.getElementById('selectfile').click();
}

document.getElementById('selectfile').onchange = function() {
    ajax_file_upload(document.getElementById('selectfile').files[0]);
};

function ajax_file_upload(file_obj) {
    if(file_obj != undefined) {
        var form_data = new FormData();
        form_data.append('file', file_obj);
        var xhttp = new XMLHttpRequest();
        xhttp.open("POST", "upload_img.php", true);
        xhttp.onload = function(event) {
            var response = this.responseText.trim();
            if (xhttp.status == 200 && !response.startsWith('error')) {
                show_uploaded_img(response);
                if (window.forumDraftAddImage) {
                    window.forumDraftAddImage(response);
                }
            } else {
                alert("Echec de l'envoi du fichier. " + (response || ''));
            }
        }

        xhttp.send(form_data);
    }
}


/* One row per uploaded picture. The link opens a new tab: leaving the page
 * would lose the form being written. */
function show_uploaded_img(url) {
    var $link = $('<a target="_blank" rel="noopener"></a>').attr('href', url).text(url);
    var $insert = $('<input type="button" value="Insérer" />').on('click', function() {
        insert_img(url);
    });
    $('#uploaded-table').append($('<tr></tr>').append($('<td></td>').append($link), $('<td></td>').append($insert))).show();
}
