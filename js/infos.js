$(document).ready(function(){

    var $previewImg = $(".preview-img img");

    $('.infos-item').click(function(e){

        $('#infos-player').hide();
        $('#preview-item').hide().fadeIn();


        $('#preview-item').find('h1').html($(this).data('name'));
        $('#preview-item .preview-text').html($(this).data('text'));
        $('#preview-item .preview-caracs').html($(this).data('caracs'));

        preload($(this).data('img'), $previewImg);
    });


    $('.infos-portrait').click(function(e){


        $('#preview-item').hide();
        $('#infos-player').fadeIn();
    });
});
