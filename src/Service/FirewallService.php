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
        $db->exe('DELETE FROM players_ips WHERE expTime <= ?', [time()]);

        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            $this->ip = $_SERVER['REMOTE_ADDR'];
            $result = $db->exe('SELECT * FROM players_ips WHERE ip = ? AND failed > 0', [$this->ip]);
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

                $db->exe('UPDATE players_ips SET failed = failed + 1, expTime = ? WHERE ip = ?', [$expTime, $ip]);
            } else {

                $db->exe('INSERT INTO players_ips (`ip`, `expTime`, `failed`) VALUES (?, ?, 1)', [$ip, $expTime]);
            }
        }
    }
}
