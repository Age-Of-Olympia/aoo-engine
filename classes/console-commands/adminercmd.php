<?php
use Classes\AdminCommand;

class AdminerCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("adminer");
        parent::setDescription(<<<EOT
Db shortcut (need db credentials)
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        return '<script>document.location = "adminer.php";</script>';
    }
}
