<?php

// Chemin vers le fichier csv
$csvFile = 'datas/private/console/track.csv';


// Définir les en-têtes HTTP pour forcer le téléchargement
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $csvFile . '"');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . basename($csvFile) . '"');

// Lire le fichier et l'envoyer au navigateur
readfile($csvFile);
exit;

