<?php
session_start();

$arr_file_types = ['image/png', 'image/gif', 'image/jpg', 'image/jpeg'];

if (!(in_array($_FILES['file']['type'], $arr_file_types))) {
    echo "error: invalid file type";
    die;
}

// Vérification de la taille du fichier
$max_file_size = 2 * 1024 * 1024; // 2 Mo en octets
if ($_FILES['file']['size'] > $max_file_size) {
    echo "error: file size exceeds 2MB";
    die;
}

if (!file_exists('img/ui/forum/uploads')) {
    mkdir('img/ui/forum/uploads', 0777, true); // Le troisième paramètre 'true' permet la création récursive
}

if (!file_exists('img/ui/forum/uploads/' . $_SESSION['playerId'])) {
    mkdir('img/ui/forum/uploads/' . $_SESSION['playerId'], 0777, true);
}

$file_name = $_FILES['file']['name'];
$file_extension = pathinfo($file_name, PATHINFO_EXTENSION);

$filename = time();

if (move_uploaded_file($_FILES['file']['tmp_name'], 'img/ui/forum/uploads/' . $_SESSION['playerId'] . '/' . $filename . '.' . $file_extension)) {
    echo 'img/ui/forum/uploads/' . $_SESSION['playerId'] . '/' . $filename . '.' . $file_extension;
} else {
    echo "error: file upload failed";
}
die;
?>
