<?php

class CronCmd extends Command
{
    public function __construct() {
        parent::__construct("cron",[new Argument('path',false)]);
        parent::setDescription(<<<EOT
Permet de jouer un cron manuellement
Exemple:
> cron daily/archive_logs
> cron "" daily
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {
        $path = "scripts/crons/$argumentValues[0]/$argumentValues[1].php";

        if (file_exists($path)) {

            ob_start();

            echo $path .'<br />';

            $db = new Db();

            include($path);

            echo '<br />cron executé';
            return ob_get_clean();
        } else {
            return 'unknown cron '.$path.', nothing done';
        }

    }
}
