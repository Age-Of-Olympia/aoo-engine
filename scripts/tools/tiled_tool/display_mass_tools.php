<?php


?><h3>Appliquer sur une zone</h3>

<div>

 <style>
        .small-input {
          max-width: 50px ;
        }
    </style>
    
<div>
    <div id="zone-tiles">
      Debut:<br/>
       x<input type="text" class="small-input" id="zone-params-begin-x" />  y<input type="text" class="small-input" id="zone-params-begin-y" />  <br/>
      Fin: <br/>
       x<input type="text" class="small-input" id="zone-params-end-x" />  y<input type="text" class="small-input" id="zone-params-end-y" />
       <br/>
       <button id="zone-apply">Appliquer</button>
    </div>
</div>


<script>
$(document).ready(function(){
  function validateZoneData(zoneData) {
    var integerRegex = /^-?\d+$/;

    for (var key in zoneData) {
      var value = zoneData[key];
      if (value === '' || !integerRegex.test(value)) {
        return false; 
      }
    }
    return true;
  }



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
           url: 'tools.php?tiled',
           data: {
             'zone': zoneData,
             'type':$selected.data('type'),
             'src':src,
             'params':params
           }, // serializes the form's elements.
           success: function(data)
           {
              document.location='tools.php?tiled&selectedTool='+$selected.data('name')+'&selectedParams='+params;
           }
         });


       }
       
   });
});
</script>
</div>



