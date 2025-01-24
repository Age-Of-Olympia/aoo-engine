<?php

class TiledCmd extends Command
{
    public function __construct() {
        parent::__construct("tiled");
        parent::setDescription(<<<EOT
permet d'afficher l'éditeur de carte
Exemple:
> tiled : affiche un lien vers l'éditeur de carte
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        return '<a href="tiled.php">tiled</a>';
    }
}
