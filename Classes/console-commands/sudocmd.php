<?php
use Classes\Command;
use Classes\Argument;
use App\Service\AdminAuthorizationService;

class SudoCmd extends Command
{
    public function __construct() {
        parent::__construct("sudo",[new Argument('level',true), new Argument('force',true)]);
        parent::setDescription(<<<EOT
Active pour la session les droits liés au compte connecté ; ils suivent
l'incarnation d'un PNJ. Sur un PJ ils restent désactivés, sauf avec
-force, valable pour ce seul PJ.
Exemple:
> sudo admin
> sudo superadmin
> sudo admin -force
> sudo off
EOT);
    }

    /** The one command open to a login whose rights are disabled. */
    public function executeIfAuthorized(array $argumentValues): string
    {
        return $this->execute($argumentValues);
    }

    public function execute(array $argumentValues): string
    {
        $args = array_map('strtolower', array_filter($argumentValues, 'is_string'));
        $force = in_array('-force', $args, true);
        $level = array_values(array_diff($args, ['-force']))[0] ?? AdminAuthorizationService::SUDO_ADMIN;

        try {
            AdminAuthorizationService::sudo($level === 'off' ? null : $level, $force);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return $level === 'off' ? 'Droits désactivés.' : 'Droits ' . $level . ' activés pour cette session.';
    }
}
