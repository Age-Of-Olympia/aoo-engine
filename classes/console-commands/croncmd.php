<?php
use App\Service\CronService;
class CronCmd extends AdminCommand
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

        if (!isset($argumentValues[1])) {
            if (strpos($argumentValues[0], '/') === false) {
                $cronService = new CronService();
                $cronService->executeCron($argumentValues[0]);
                return '';
            } else {
                        $path = "scripts/crons/$argumentValues[0].php";
            }
        }
        else{
             $path = "scripts/crons/$argumentValues[0]/$argumentValues[1].php";
        }
        

        if (file_exists($path)) {

            ob_start();

            echo $path .'<br />';

            $db = new Db();

            include($path);

            echo '<br />cron executé';
            return ob_get_clean();
        } else {
            $this->result->Error('Le cron '.$path.' n\'existe pas');
            return '';
        }
    }
}
