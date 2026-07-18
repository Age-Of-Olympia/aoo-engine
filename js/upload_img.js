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
                $('#uploaded-table tr:last').after('<tr><td><a href="'+response+'">'+response+'</a></td><td><input type="button" value="Insérer" OnClick="insert_img(\''+response+'\');" /></td></tr>');
                $('#uploaded-table').show();
            } else {
                alert("Echec de l'envoi du fichier. " + (response || ''));
            }
        }

        xhttp.send(form_data);
    }
}

