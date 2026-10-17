<?php

namespace App\Service;

use Classes\Db;
use Classes\File;
use Throwable;

class CronService
{
    private AuditService $auditService;
    private ?string $running = null;
    private bool $finished = false;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * Runs every script of scripts/crons/$type in order. Each script leaves a
     * start and an end line in the audit table, all under the run's audit_key;
     * a fatal error or an exit() inside a script is caught at shutdown, so a
     * run that never reaches "done" still says where it stopped.
     */
    public function executeCron(string $type): void
    {
        $runStart = microtime(true);
        $this->audit('Cron '.$type.' started (pid '.getmypid().', memory_limit '.ini_get('memory_limit')
            .', max_execution_time '.ini_get('max_execution_time').')');
        register_shutdown_function(fn () => $this->auditUnfinished($type));

        // $db is in fact used in the loaded scripts !
        $db = new Db();
        $path = __DIR__.'/../../scripts/crons/'.$type.'/';
        $failures = 0;
        foreach (File::scan_dir($path) as $file) {
            $this->running = $type.'/'.$file;
            $start = microtime(true);
            $this->audit('cron '.$this->running.' started');
            try {
                echo $file .' ';
                include($path .'/'. $file);
                echo ' <br />';
                $this->audit('cron '.$this->running.' ok in '.$this->since($start));
            } catch (Throwable $e) {
                $failures++;
                $this->audit('cron '.$this->running.' FAILED in '.$this->since($start).': '
                    .get_class($e).': '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            }
        }
        $this->running = null;
        $this->finished = true;

        $log = 'cron '.$type.' done '.date('d/m/Y H:i:s').' in '.$this->since($runStart)
            .($failures ? ', '.$failures.' script(s) failed' : '');
        $this->audit($log);
        echo $log;
    }

    private function auditUnfinished(string $type): void
    {
        if ($this->finished) {
            return;
        }
        $error = error_get_last();
        $this->audit('cron '.$type.' INTERRUPTED during '.($this->running ?? 'startup').': '
            .($error ? $error['message'].' at '.$error['file'].':'.$error['line'] : 'exit without error'));
    }

    /** An audit write must never be what kills the run (closed EntityManager, lost connection). */
    private function audit(string $details): void
    {
        try {
            $this->auditService->addAuditLog($details);
        } catch (Throwable $e) {
            error_log($details.' [audit write failed: '.$e->getMessage().']');
        }
    }

    private function since(float $start): string
    {
        return round(microtime(true) - $start, 2).'s';
    }
}
