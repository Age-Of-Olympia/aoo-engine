<?php

@unlink('datas/private/players/'. $player->id .'.msg.html');

$player->get_coords();

echo '
<div id="tooltip"><div class="text">tooltip</div><div class="tooltip-next"><a href="#" class="next">[suite]</a></div></div>
';

?>
<script>
window.playerId = <?php echo $_SESSION['playerId'] ?>;
window.dataCoords = "<?php echo $player->coords->x+1 ?>,<?php echo $player->coords->y ?>";
</script>
<script src="js/tutorial.js"></script>
