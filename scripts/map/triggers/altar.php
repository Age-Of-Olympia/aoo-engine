<?php

/*
 * Marcher sur un autel ne fait rien — et surtout ne l'efface plus.
 *
 * Ce fichier supprimait le déclencheur au premier pas, « puisqu'il n'est plus
 * lié à une structure ». Il l'était : c'est lui qui porte le dieu de l'autel,
 * donc le classement de la Foi. Un pas suffisait à retirer un autel du monde,
 * sans trace ; sur une case devenue nue, il ne restait rien à voir.
 *
 * Le fichier reste tant que des lignes `altar` existent : `go.php` refuse le
 * pas quand le script d'un déclencheur manque (« error trigger path »), donc
 * le supprimer maintenant murerait ces cases. Il partira avec la dernière
 * ligne, quand l'autel sera une entité.
 */
