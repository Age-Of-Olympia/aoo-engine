
function open_console(){
    let consoleTextArea = $('#console-wrapper');
    if(consoleTextArea.length === 0){
        document.body.innerHTML += '<div id="console-wrapper">'
        + '<div id="console"><div id="console-content"></div></div>'
        + '<input type="text" id="input-line" />'
        + '</div>'
    }else{
        consoleTextArea.show();
    }

    $('#input-line').focus();
}

function bind_console_keys(body){

    body.addEventListener('keydown', function(e) {
        let consoleTextArea = $('#console-wrapper');
         switch (e.code) {
             case 'Backquote':
                 if($('#console-wrapper').is(':visible')){
                    $('#console-wrapper').hide();
                    return false;
                 }
                 open_console();
                 e.preventDefault();
                 break;
             case 'Tab':
                 if(consoleTextArea.is(':visible')) {
                     completion($('#input-line'));
                     e.preventDefault();
                 }
                 break;
             case 'NumpadEnter':
             case 'Enter':
                 if(consoleTextArea.is(':visible')) {
                     let line = $('#input-line').val();
                     if(line.length>0){
                         $('#console-content').append('<span class="request">' + line + '</span>');
                         submit_command(line);
                     }
                     e.preventDefault();
                 }
                 break;
             case 'ArrowUp':
                 if(consoleTextArea.is(':visible')) {
                   $('#input-line').val($('#console .request').last().text()).focus();
                   e.preventDefault();
                 }
                 break;
             default:
                 break;
         }

    });
}

function submit_command(cmdLine){
    $.ajax({
        url: 'console.php',
        type: 'POST',
        data: { cmdLine: cmdLine },
        success: function(response) {
            let responseObj = JSON.parse(response);
            if(responseObj.error){
                $('#console-content').append('<span class="response-error">'+responseObj.error+ '</span>');
            }else{
                $('#console-content').append('<span class="response">'+responseObj.message+ '<br />'+ responseObj.result+'</span>');
                $('#input-line').val('');
            }
            $('#console').scrollTop($('#console')[0].scrollHeight);

        },
        error: function(xhr, status, error) {
            $('#console-content').append('<span class="response-error">Error : '+error+ '</span>');
            $('#console').scrollTop($('#console')[0].scrollHeight);

        }
    });
}


function completion(cmdLine){
    $.ajax({
        url: 'console.php',
        type: 'POST',
        data: { cmdLine: cmdLine.val(), completion:1 },
        success: function(response) {
            let responseObj = JSON.parse(response);
            if(responseObj.suggestions.length===1){
                cmdLine.val(responseObj.suggestions[0] +' ');
            }else if (responseObj.suggestions.length>1){
                let $console = $('#console-content');
                $console.append('<span class="response-completion">');
                responseObj.suggestions.forEach(function(item) {
                    $console.append(item + ' ');
                });
                $console.append('</span>');
            }
        }

    });
}
