<?php

namespace App\Service;

use Classes\File;
use mysqli_sql_exception;

class DataBaseUpdateService extends BaseService
{
    public function updateDb(): bool {
        ob_start();
        $res = $this->executeAndLog(
            function() {
                $res = false;
                $updateDir = $_SERVER['DOCUMENT_ROOT'].'/db/updates';
                $doneDir = $_SERVER['DOCUMENT_ROOT'].'/db/updates_done';
                $doneFiles = File::scan_dir($doneDir);

                $sql = '';

                $auditService = new AuditService();

                foreach (File::scan_dir($updateDir) as $e) {
                    echo $e;
                    if (in_array($e, $doneFiles)) {
                        echo ' skipped';
                    } else {
                        $data = file_get_contents($updateDir . '/' . $e);
                        $sql .= $data;

                        // cp this update to updates_done
                        $myfile = fopen($doneDir . '/' . $e, "w") or die("Unable to open file!");
                        fwrite($myfile, $data);
                        fclose($myfile);
                        $auditService->addAuditLog("Filed copied to updates_done: ".$e);

                        echo ' <font color="blue">done!</font>';
                    }
                    echo '<br />';
                }

                if ($sql != '') {
                    try {
                        db()->multi_query($sql);
                    } catch (mysqli_sql_exception $e) {
                        $auditService->addAuditLog("Error: ".$e->getMessage());
                        $res = false;
                        return $res;
                    }
                    echo '<font color="red">db updated</font>';
                } else {
                    $res = true;
                    echo 'db not updated';
                }
                return $res;
            }
        );
        return $res;
    }
}
