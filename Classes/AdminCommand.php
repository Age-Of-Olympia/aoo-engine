<?php
namespace Classes;
use App\Service\AdminAuthorizationService;

abstract class AdminCommand extends Command
{
    public function executeIfAuthorized( array $argumentValues ): string {
        AdminAuthorizationService::DoSuperAdminCheck();
        return $this->execute($argumentValues);
    }
}
