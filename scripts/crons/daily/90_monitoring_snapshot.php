<?php

/* Relevé du jour pour le monitoring (or, XP, objets, admins, tailles de
 * tables) : en dernier, pour que l'écart suivant ne compte que le jeu. */
(new \App\Service\Monitoring\MonitoringSnapshotService())->take();

echo 'relevé monitoring enregistré';
