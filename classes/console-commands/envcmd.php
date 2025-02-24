<?php

class EnvCmd extends Command
{
    public function __construct() {
        parent::__construct("env",[new Argument('varibleName',false), new Argument('value',false)]);
        parent::setDescription(<<<EOT
defini une variable d'environnement pour l'utilisation d'un lot de commandes.
Exemple:
> env mat 1
> env pnjname Orcrist
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
       $GLOBALS["consoleENV"][$argumentValues[0]] = $argumentValues[1];

        return 'Variable d\'environnement '.$argumentValues[0].' définie à '.$argumentValues[1];
    }
}
