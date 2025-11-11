<?php


?><span style="font-size: 11px; font-weight: bold;">Zone:</span>
<div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
    <span style="font-size: 10px;">Début</span>
    x<input type="text" style="width: 40px; padding: 2px;" id="zone-params-begin-x" />
    y<input type="text" style="width: 40px; padding: 2px;" id="zone-params-begin-y" />
    <span style="font-size: 10px;">Fin</span>
    x<input type="text" style="width: 40px; padding: 2px;" id="zone-params-end-x" />
    y<input type="text" style="width: 40px; padding: 2px;" id="zone-params-end-y" />
    <button id="zone-apply" style="padding: 4px 8px; font-size: 11px;">Appliquer</button>
</div>


<script>
$(document).ready(function(){


   $("#zone-apply").click(function(e){
       var $selected = $(".selected");
       if(!$selected[0]){
          alert("Sélectionnez une tile avant de cliquer sur ce bouton et elle sera appliquée sur les coordonnées indiquées.")         
       }else{

         var src = $selected.attr('src');

         var params = '';

         if($selected.hasClass('ele')){

           src = $selected.data('element');
         }

         if($selected.hasClass('select-name')){

           src = $selected.data('name');
         }

         if($selected.data('params') != null){

           params = $('#'+ $selected.data('type') +'-params').val();
         }

         var zoneData = {  beginX : $("#zone-params-begin-x").val(),
           beginY : $("#zone-params-begin-y").val(),
           endX : $("#zone-params-end-x").val(),
           endY : $("#zone-params-end-y").val()
         };

         if (!validateZoneData(zoneData)) {
            alert("Erreur : Toutes les coordonnées doivent être saisies et des entiers !");
            return ;
         }

         $.ajax({
           type: "POST",
           url: 'tiled.php',
           data: {
             'zone': zoneData,
             'type':$selected.data('type'),
             'src':src,
             'params':params
           }, // serializes the form's elements.
           success: function(data)
           {
              document.location='tiled.php?selectedTool='+$selected.data('name')+'&selectedParams='+params;
           }
         });


       }
       
   });
});
</script>
</div>



