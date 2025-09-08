<?php

namespace App\Service;

use Classes\Db;
use Classes\Player;

class FirewallService
{
    private int $previousFailedCount = 0;
    public string $ip = '';
    public function __construct() {}
    public function TryPassFirewall()
    {
        $db = new Db();


        // firewall
        $sql = 'DELETE FROM players_ips WHERE expTime <= ' . time() . '';
        $db->exe($sql);

        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
            $sql = 'SELECT * FROM players_ips WHERE ip = "' .  $this->ip . '" AND failed > 0 ';
            $result = $db->exe($sql);
            $row_ip = $result->fetch_assoc();

            $this->previousFailedCount = (is_array($row_ip)) ? $row_ip['failed'] : 0;

            $msg = 'Trop de tentatives!
  Attendez 5 minutes avant de réessayer.';

            if ($this->previousFailedCount >= 3) exit($msg);
        }
    }
    public function RecordFailedAttempt()
    {

        $db = new Db();

        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            $ip = $_SERVER['REMOTE_ADDR'];

            $expTime = time() + 300;

            // reccord the fail for firewall
            if ($this->previousFailedCount > 0) {

                $sql = 'UPDATE players_ips SET failed = failed + 1, expTime = ' . $expTime . ' WHERE ip = "' . $ip . '" ';
                $db->exe($sql);
            } else {

                $sql = '
        INSERT INTO players_ips
        (`ip`,`expTime`,`failed`)
        VALUES("' . $ip . '",' . $expTime . ',1);
        ';
                $db->exe($sql);
            }
        }
    }
}
