<?php

namespace App\Service;

use App\Service\BaseService;
use Db;
use Exception;
use File;
use Throwable;

class CronService extends BaseService
{
    public function executeCron($type): void {
        $this->auditService->addAuditLog('Cron '.$type.' started !');
        // $db is in fact used in the loaded scripts !
        $db = new Db();
        $path = __DIR__.'/../../scripts/crons/'.$type.'/';
        foreach(File::scan_dir($path) as $file){
            try {
                echo $file .' ';
                include($path .'/'. $file);
                echo ' <br />';
            } catch (Exception $e) {
                $this->auditService->addAuditLog($e->getMessage());
            }
        }
        $log = 'cron '.$type.' done '. date('d/m/Y H:i:s');
        $this->auditService->addAuditLog($log);
        echo $log;
    }

}

