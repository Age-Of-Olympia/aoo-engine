<?php

define('NO_LOGIN', true);

use App\Service\DataBaseUpdateService;
use App\Service\AuditService;
use App\Service\AdminAuthorizationService;


require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// config-console.php is an unversioned per-server file. Guard the include so a
// freshly provisioned docroot (e.g. a new subdomain) does not fatal before the
// passphrase check even runs.
$consoleConfig = $_SERVER['DOCUMENT_ROOT'] . '/config/config-console.php';
if (is_file($consoleConfig)) {
    require_once($consoleConfig);
}

$auditService = new AuditService();

// Log the start of the deployment attempt
$firstLogId = $auditService->addAuditLog("Deploying attempt");

// Resolve which environment this docroot serves. The deploy target is always
// THIS instance's own docroot, never a hardcoded ~/public_html — so a deploy
// triggered on one subdomain can never overwrite a sibling environment.
$envInfo = aoo_deploy_env($_SERVER['HTTP_HOST'] ?? null);
$isCI    = isset($_GET["ci"]);

// Guardrail: production never auto-deploys via CI. A misfired pipeline pointed
// at the prod host is refused outright; prod stays manual (super-admin) only.
if ($envInfo['is_prod'] && $isCI) {
    http_response_code(403);
    $auditService->addAuditLog("Refused: CI auto-deploy attempt on production");
    $auditService->setCurrentAuditKey(null);
    exit("Production does not accept CI auto-deploy.");
}

// Token now travels in a header instead of the URL query string, so it no
// longer lands in Apache access logs.
$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';

if (isset($_GET["type"]) && $providedToken !== '') {
    $passPhrase = getPassphrase($isCI, $auditService);

    // Start output buffering
    ob_start();
    echo "Deploying " . htmlspecialchars($_GET["type"]);

    if (validatePassphrase($passPhrase, $providedToken)) {
        deploy($_GET["type"], $envInfo, $auditService);
    } else {
        http_response_code(403);
        $auditService->addAuditLog("Refused: invalid deploy token");
        echo "<br />Invalid deploy token.";
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
        AdminAuthorizationService::DoSuperAdminCheck();
        $passPhrase = rtrim(file_get_contents('/home/' . get_current_user() . '/etc/passphrase'), "\r\n");
        return $passPhrase;
    }
}

/**
 * Validate the provided token against the stored passphrase.
 *
 * Uses hash_equals to avoid leaking the passphrase through timing.
 *
 * @param string $storedPassphrase The stored passphrase.
 * @param string $providedToken The token supplied by the caller.
 * @return bool Whether the token is valid.
 */
function validatePassphrase(string $storedPassphrase, string $providedToken): bool {
    return $storedPassphrase !== "" && hash_equals($storedPassphrase, $providedToken);
}

/**
 * Build the environment passed to the deploy shell scripts.
 *
 * DOCROOT/SRC/EXPECT_BRANCH replace the paths the scripts used to hardcode.
 *
 * @param array{env:string,branch:?string,is_prod:bool,session_name:string} $envInfo
 * @return array<string,string>
 */
function deployEnvVars(array $envInfo): array {
    $src = rtrim((string) getenv('HOME'), '/') . '/deploy/' . $envInfo['env'];
    return [
        'DOCROOT'       => $_SERVER['DOCUMENT_ROOT'],
        'SRC'           => $src,
        // Prod deploys from a tag (detached HEAD) -> empty = skip branch check.
        // Non-prod envs are pinned to their branch by the scripts' assertion.
        'EXPECT_BRANCH' => $envInfo['is_prod'] ? '' : (string) ($envInfo['branch'] ?? ''),
    ];
}

/**
 * Run a deploy shell script with the resolved environment, returning its output
 * (with the trailing exit code appended by the caller's "echo $?").
 *
 * @param array<string,string> $envVars
 */
function runDeployScript(string $script, array $envVars): string {
    $prefix = '';
    foreach ($envVars as $name => $value) {
        $prefix .= $name . '=' . escapeshellarg($value) . ' ';
    }
    return (string) shell_exec($prefix . escapeshellarg($script) . " 2>&1; echo $?");
}

/**
 * Execute the deployment process.
 *
 * @param string $type The type of deployment.
 * @param array{env:string,branch:?string,is_prod:bool,session_name:string} $envInfo
 * @param AuditService $auditService The audit service instance.
 */
function deploy(string $type, array $envInfo, AuditService $auditService) {
    $envVars = deployEnvVars($envInfo);

    if ($type === "code") {
        if (!handleCodeDeployment($envVars, $auditService)) {
            return;
        }
    }

    $output = runDeployScript($_SERVER['DOCUMENT_ROOT'] . "/scripts/deploy_{$type}.sh", $envVars);
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
 * Handle the code deployment process (SQL/migrations first, then code copy).
 *
 * @param array<string,string> $envVars
 * @param AuditService $auditService The audit service instance.
 * @return bool Whether the SQL phase succeeded.
 */
function handleCodeDeployment(array $envVars, AuditService $auditService): bool {
    $outputDb = runDeployScript($_SERVER['DOCUMENT_ROOT'] . "/scripts/deploy_sql.sh", $envVars);
    echo "<br />" . htmlspecialchars($outputDb) . "<br />";

    $outputDbReturnCode = mb_substr($outputDb, -2);
    if ($outputDbReturnCode != 0) {
        http_response_code(500);
        $auditService->addAuditLog("Result: " . $outputDb);
        echo "<br />SQL file copy error.";
        return false;
    }

    $dbuService = new DataBaseUpdateService();
    $dbuService->setCurrentAuditKey($auditService->getCurrentAuditKey());
    $res = $dbuService->updateDb();
    if (!$res) {
        http_response_code(500);
        echo "<br />SQL Error.";
        return false;
    }

    echo "<br />SQL Done.";
    return true;
}
