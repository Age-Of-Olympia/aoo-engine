<?php

define('NO_LOGIN', true);

use App\Service\DataBaseUpdateService;
use App\Service\AuditService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/config-console.php');

$auditService = new AuditService();

// Log the start of the deployment attempt
$firstLogId = $auditService->addAuditLog("Deploying attempt");

if (isset($_GET["type"]) && isset($_GET["passphrase"])) {
    $passPhrase = getPassphrase(isset($_GET["ci"]), $auditService);

    // Start output buffering
    ob_start();
    echo "Deploying " . htmlspecialchars($_GET["type"]);

    if (validatePassphrase($passPhrase, $_GET["passphrase"])) {
        deploy($_GET["type"], $auditService);
    }
}

// Log the end of the deployment attempt
$auditService->addAuditLog("Deploying attempt finished");
$auditService->setCurrentAuditKey(null);

/**
 * Retrieve the passphrase based on the CI flag.
 *
 * @param bool $isCI Whether the deployment is via CI.
 * @return string The passphrase.
 */
function getPassphrase(bool $isCI, AuditService $auditService): string {
    $auditMessage = $isCI ? "Deploy with CI" : "Deploy manually";
    $auditService->addAuditLog($auditMessage);

    if ($isCI) {
        $passPhrase = rtrim(file_get_contents('/home/' . get_current_user() . '/etc/ci_passphrase'), "\r\n");
        return $passPhrase;
    } else {
        include($_SERVER['DOCUMENT_ROOT'] . '/checks/super-admin-check.php');
        $passPhrase = rtrim(file_get_contents('/home/' . get_current_user() . '/etc/passphrase'), "\r\n");
        return $passPhrase;
    }
}

/**
 * Validate the passphrase.
 *
 * @param string $storedPassphrase The stored passphrase.
 * @param string $providedPassphrase The provided passphrase.
 * @return bool Whether the passphrase is valid.
 */
function validatePassphrase(string $storedPassphrase, string $providedPassphrase): bool {
    return $storedPassphrase !== "" && strcmp($storedPassphrase, $providedPassphrase) === 0;
}

/**
 * Execute the deployment process.
 *
 * @param string $type The type of deployment.
 * @param AuditService $auditService The audit service instance.
 */
function deploy(string $type, AuditService $auditService) {
    if ($type === "code") {
        handleCodeDeployment($auditService);
    }

    $output = shell_exec($_SERVER['DOCUMENT_ROOT'] . "/scripts/deploy_{$type}.sh 2>&1; echo $?");
    echo "<br />" . htmlspecialchars($output);

    $outputReturnCode = mb_substr($output, -2);
    if ($outputReturnCode != 0) {
        http_response_code(500);
        $auditService->addAuditLog("Result: " . $output);
        echo "<br />Code file copy error.";
        return;
    }

    echo "<br />Code Done.";
}

/**
 * Handle the code deployment process.
 *
 * @param AuditService $auditService The audit service instance.
 */
function handleCodeDeployment(AuditService $auditService) {
    $outputDb = shell_exec($_SERVER['DOCUMENT_ROOT'] . "/scripts/deploy_sql.sh; echo $?");
    echo "<br />" . htmlspecialchars($outputDb) . "<br />";

    $outputDbReturnCode = mb_substr($outputDb, -2);
    if ($outputDbReturnCode != 0) {
        http_response_code(500);
        $auditService->addAuditLog("Result: " . $outputDb);
        echo "<br />SQL file copy error.";
        return;
    }

    $dbuService = new DataBaseUpdateService();
    $dbuService->setCurrentAuditKey($auditService->getCurrentAuditKey());
    $res = $dbuService->updateDb();
    if (!$res) {
        http_response_code(500);
        echo "<br />SQL Error.";
        return;
    }

    echo "<br />SQL Done.";
}
?>
