<?php

echo '<textarea style="width: 100vw; height: 50vw;">';

echo '====== Console ======

En jeu, connecté avec votre compte Admin, appuyez sur ² pour afficher la console.

Voici la liste des commandes disponibles (* paramètres optionnels)
';


$result = array();


$dir = 'classes/console-commands/'; // Remplacez par le chemin de votre dossier

if (is_dir($dir)) {
    $files = scandir($dir);

    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_file($filePath)) {


            $content = file_get_contents($filePath);


            // Trouver la ligne contenant parent::__construct(...)
            if (preg_match('/parent::__construct\("([^"]+)",\s*\[(.*?)\]\);/s', $content, $matches)) {
                $command = $matches[1];
                $arguments = $matches[2];

                // Trouver chaque argument avec son état true ou false
                preg_match_all('/new Argument\(\s*\'([^\']+)\'\s*,\s*(true|false)\s*\)/', $arguments, $arg_matches);

                $formatted_args = [];
                foreach ($arg_matches[1] as $index => $arg) {
                    $state = $arg_matches[2][$index];
                    $formatted_args[] = $arg . '(' . $state . ')';
                }

                // Afficher les arguments formatés avec leurs états
                $formatted_command = $command . '(' . $state . '), ' . implode(', ', $formatted_args);
                $cmds = implode(' ', $formatted_args);

                $cmds = str_replace('(false)', '', $cmds);
                $cmds = str_replace('(true)', '*', $cmds);
            }


            if(trim($cmds) == ''){

                continue;
            }


            $content = nl2br(trim($content));

            $content = str_replace('<br />', '&#92;&#92;', $content);

            $file = str_replace('cmd.php', '', $file);

            // Trouver toutes les occurrences entre /* et */
            preg_match_all('/\/\*(.*?)\*\//s', $content, $matches);

            foreach ($matches[1] as $match) {

                // Retirer les * en début de ligne et les espaces en trop
                $cleaned_match = preg_replace('/^\s*\*\s?/m', '', $match);
                $data = "\n===== $file =====\n";
                $data .= "''$file $cmds''\n\n";
                $data .= trim($cleaned_match) . "\n";

                $result[$file] = $data;
            }
        }



    }

    ksort($result);

    echo implode('', $result);

} else {
    echo "Le chemin spécifié n'est pas un dossier valide.";
}

echo '</textarea>';
