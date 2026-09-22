<?php

namespace App\Service\ImportExport;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * How far a plan bundle got.
 *
 * A plan import is a list of steps, each committed on its own; the cursor
 * moves inside the step's own transaction, so a crash leaves the world at a
 * step boundary and the same bundle resumes there.
 *
 * The bundle is identified by a fingerprint of its content: edit it and the
 * run starts over, which is what an edited bundle means.
 */
final class PlanImportProgress
{
    private ?Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    /** The fingerprint of one plan payload — stable across requests and machines. */
    public static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /** The step this bundle stopped at, 0 when it never ran. */
    public function stepOf(string $fingerprint, string $plan): int
    {
        $step = $this->conn()->fetchOne(
            'SELECT step FROM plan_import_progress WHERE fingerprint = ? AND plan = ?',
            [$fingerprint, $plan]
        );

        return $step === false ? 0 : (int) $step;
    }

    /** Records the step to resume at — called inside the step's transaction. */
    public function record(string $fingerprint, string $plan, int $nextStep, int $total): void
    {
        $this->conn()->executeStatement(
            'INSERT INTO plan_import_progress (fingerprint, plan, step, total, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE step = VALUES(step), total = VALUES(total), updated_at = NOW()',
            [$fingerprint, $plan, $nextStep, $total]
        );
    }

    /** The run is over: nothing left to resume. */
    public function clear(string $fingerprint, string $plan): void
    {
        $this->conn()->executeStatement(
            'DELETE FROM plan_import_progress WHERE fingerprint = ? AND plan = ?',
            [$fingerprint, $plan]
        );
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }
}
