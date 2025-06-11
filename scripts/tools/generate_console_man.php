<?php
use Classes\CommandFactory;
require_once('config.php');
echo '<textarea style="width: 100vw; height: 50vw;">';

echo '====== Console ======

En jeu, connecté avec votre compte Admin, appuyez sur ² pour afficher la console.

Voici la liste des commandes disponibles (* paramètres optionnels)
';

$factory = CommandFactory::initCommmandFactory();
$result = array();

foreach ($factory->getCommands() as $command ){
    $commandName = $command->getName();

    $data = "\n===== $commandName =====\n";

    $formatted_args = [];
    foreach ($command->getArguments() as $argument){
        $formatted_args[] = $argument->getName().($argument->isOptional()?'*':'');
    }
    $cmds = implode(' ', $formatted_args);
    $data .= "''$commandName $cmds''\n\n";
    $data .= $command->getDescription() . "\n";

    $result[$command->getName()] = $data;
}

ksort($result);

echo implode('', $result);

echo '</textarea>';
