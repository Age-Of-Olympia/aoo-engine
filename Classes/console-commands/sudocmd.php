<?php
use Classes\AdminCommand;

class SudoCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("sudo");
        parent::setDescription(<<<EOT
passe votre session en mode super administrateur.
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $this->result->log('Vous êtes maintenant en mode super administrateur.');
        return '';
    }
}
