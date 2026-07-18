<?php
define('NO_LOGIN', true);
require_once(__DIR__.'/config.php');

if (empty($_SESSION['playerId'])) {
    echo "error: not authenticated";
    die;
}
$playerId = $_SESSION['playerId'];

// Types MIME autorisés. Pas de webm : [img] rend un <img>, qui n'affiche pas de vidéo.
$arr_file_types = ['image/png', 'image/gif', 'image/jpg', 'image/jpeg', 'image/webp', 'image/jfif', 'image/avif'];

// Extensions autorisées (jfif est du jpeg : les navigateurs envoient image/jpeg)
$arr_file_extensions = ['png', 'gif', 'jpg', 'jpeg', 'webp', 'jfif', 'avif'];

// Vérification du type MIME
if (!in_array($_FILES['file']['type'], $arr_file_types)) {
    echo "error: invalid file type";
    die;
}

// Vérification de la taille du fichier (limite de 2 Mo)
$max_file_size = 2 * 1024 * 1024; // 2 Mo en octets
if ($_FILES['file']['size'] > $max_file_size) {
    echo "error: file size exceeds 2MB";
    die;
}

// Vérification de l'extension du fichier
$file_name = $_FILES['file']['name'];
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)); // Extension du fichier en minuscule

if (!in_array($file_extension, $arr_file_extensions)) {
    echo "error: invalid file extension";
    die;
}

// Création des répertoires si nécessaire
if (!file_exists('img/ui/forum/uploads')) {
    mkdir('img/ui/forum/uploads', 0777, true); // Création récursive des dossiers
}

if (!file_exists('img/ui/forum/uploads/' . $playerId)) {
    mkdir('img/ui/forum/uploads/' . $playerId, 0777, true);
}

// Génération d'un nom de fichier unique
$filename = md5_file($_FILES['file']['tmp_name']); // Nom de fichier basé sur le hash MD5

$finalPath = 'img/ui/forum/uploads/' . $playerId . '/' . $filename . '.' . $file_extension;

// Vérification si le fichier existe déjà
if (file_exists($finalPath)) {
    echo $finalPath;
}
// Déplacement du fichier vers son emplacement final
elseif (move_uploaded_file($_FILES['file']['tmp_name'], $finalPath)) {
    echo $finalPath;
} else {
    echo "error: file upload failed";
}
die;
?>
