<?php
use App\Service\Map\ResourceObjectService;

/* Le cycle d'état d'une ressource, depuis l'éditeur.
 *
 * Il courait sur trois valeurs de `damages` — normal, récoltable, épuisé —
 * du temps où une seule table portait les murs ET les ressources, et où le
 * signe du nombre disait lequel des deux on avait sous le pinceau. Une
 * entité de type « resource » EST récoltable ; ce qui ne l'est pas est une
 * structure, et ne passe plus par ce bouton. Il ne reste donc que deux
 * états, debout ou épuisée, et le même clic pour aller de l'un à l'autre.
 *
 * Muet, comme l'était la requête qu'il remplace : tiled.php répond
 * « harvest » et l'éditeur relit la case. */
(new ResourceObjectService())->cycleState((int) $coordsId);
