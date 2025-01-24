<?php
abstract class AdminCommand extends Command
{
    public function executeIfAuthorized( array $argumentValues ): string {
        include $_SERVER['DOCUMENT_ROOT'].'/checks/super-admin-check.php';
        return $this->execute($argumentValues);
    }
}
