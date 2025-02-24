
function create_console(){

    document.body.innerHTML += '<div id="console-wrapper">'
        + '<div id="console"><div id="console-content"></div></div>'
        + '<input type="text" id="input-line" />'
        + '<button class="console-button" OnClick="submit_cmd()">ok</button>'
        + '</div>';
}

function submit_cmd(){

    let line = $('#input-line').val();
    if(line.length>0){
        $('#console-content').append('<span class="request">' + line + '</span>');
        submit_command(line);
        if(!window.cmdHistory){
            window.cmdHistory=array();
        }
        window.cmdHistory.push(line);   
        window.historyCursor = window.cmdHistory.length;
    }
}

function open_console(){
    let consoleTextArea = $('#console-wrapper');
    if(consoleTextArea.length === 0){
        create_console();
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
                    document.location.reload();
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
                     submit_cmd();
                    e.preventDefault();
                 }
                 break;
             case 'ArrowUp':
                 if(consoleTextArea.is(':visible')) {

                    if(window.cmdHistory == null){

                        $.ajax({
                            type: "POST",
                            url: 'console.php',
                            data: {'cmdHistory': 1},
                            success: function(data)
                            {
                                // alert(data);
                                window.cmdHistory = data.split('|');

                                window.historyCursor = window.cmdHistory.length-1;

                                $('#input-line').val(window.cmdHistory[window.historyCursor]);
                            }
                        });
                    }
                    else{

                        if(window.historyCursor == 0){
                            return false;
                        }

                        window.historyCursor--;

                        $('#input-line')
                        .val(window.cmdHistory[window.historyCursor])
                        .focus();
                    }
                    e.preventDefault();
                 }
                 break;
             case 'ArrowDown':
                if(window.cmdHistory == null){
                    return false;
                }
                if(window.cmdHistory.length <= window.historyCursor+1){
                    $('#input-line').val('');

                    window.historyCursor = window.cmdHistory.length;
                    return false;
                }

                window.historyCursor++;

                $('#input-line')
                .val(window.cmdHistory[window.historyCursor])
                .focus();

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
            let responseObj;
            try{
                responseObj=JSON.parse(response);
            }catch(e){
                responseObj=[{error:'Error: '+response}];
            }
            let hadAnyError=false;
            
            for(let i=0; i<responseObj.length; i++){
                if(responseObj[i].error){
                    hadAnyError=true;
                    $('#console-content').append('<span class="response-error">'+responseObj[i].error+ '</span>');
                }else{
                    $('#console-content').append('<span class="response">'+responseObj[i].message+ '<br />'+ responseObj[i].result+'</span>');
                }
            }

            if(!hadAnyError)
            {
                $('#input-line').val('');
            }
            scrollDown();

        },
        error: function(xhr, status, error) {
            $('#console-content').append('<span class="response-error">Error : '+error+ '</span>');
            scrollDown();
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
                scrollDown();
            }
        }

    });
}

function scrollDown(){
    $('#console').scrollTop($('#console')[0].scrollHeight);
}
