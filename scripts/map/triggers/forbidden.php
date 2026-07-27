<?php

/**
 * Case interdite — n'intervient plus dans le refus du PAS.
 *
 * Celui-ci est rendu en amont par App\Service\Map\TileOccupancyService, avec
 * les autres motifs : go.php s'arrête avant d'atteindre la boucle des
 * déclencheurs. Ce fichier reste parce que cette boucle exige qu'un script
 * existe pour chaque nom de déclencheur posé sur la carte — sans quoi elle
 * répond `error trigger path`.
 *
 * Il ne doit plus servir de garde : une garde qui bloque en émettant du
 * JavaScript et en tuant la requête n'est ni testable, ni composable.
 */

echo '
<script>
alert("Impossible de se rendre à cet endroit.");
document.location.reload();
</script>
';

exit();
