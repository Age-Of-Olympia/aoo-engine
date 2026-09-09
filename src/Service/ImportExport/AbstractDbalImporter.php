<?php

namespace App\Service\ImportExport;

use App\Interface\ObjectImporterInterface;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Squelette commun des importeurs DBAL à clé naturelle (`name`) —
 * revue 2026-07-18 : ItemImporter et RecipeImporter dupliquaient
 * verbatim preview/import/transactionnel/dédoublonnage.
 *
 * Contrat : collect() valide le lot et remplit le rapport
 * (création/mise à jour/rejets), apply() écrit UN payload — la
 * transaction tout-ou-rien et la convention « un échec d'écriture
 * REMONTE » (jamais un rapport contradictoire) vivent ici. Le pendant
 * Doctrine (races, factions) reste AbstractObjectImporter.
 */
abstract class AbstractDbalImporter implements ObjectImporterInterface
{
    private ?Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    final protected function connection(): Connection
    {
        return $this->connection ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    final public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->collect($objects, $report);

        return $report;
    }

    final public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $payloads = $this->collect($objects, $report);

        if ($report->hasRejections()) {
            return $report;
        }

        $this->connection()->transactional(function (Connection $conn) use ($payloads, $report): void {
            foreach ($payloads as $payload) {
                $this->apply($conn, $payload, $report);
            }
        });

        $this->afterImport($payloads);

        return $report;
    }

    /**
     * Hook post-transaction (invalidation de caches, fichiers…) — no-op par
     * défaut.
     *
     * @param array<int, array<string, mixed>> $payloads ceux qui viennent d'être écrits
     */
    protected function afterImport(array $payloads): void
    {
    }

    /**
     * Vrai (et rejet enregistré) quand $key a déjà été acceptée dans ce lot :
     * la clé naturelle est unique dans un bundle.
     *
     * @param array<string, true> $seen
     */
    final protected function isDuplicate(ImportReport $report, array &$seen, string $key): bool
    {
        if (isset($seen[$key])) {
            $report->reject($key, 'Doublon : « ' . $key . " » apparaît plusieurs fois dans le lot.");
            return true;
        }
        $seen[$key] = true;

        return false;
    }

    /**
     * Valide le lot : remplit le rapport (addCreated/addUpdated/reject)
     * et retourne les payloads normalisés prêts pour apply().
     *
     * @param array<int, mixed> $objects
     * @return array<int, array<string, mixed>>
     */
    abstract protected function collect(array $objects, ImportReport $report): array;

    /**
     * Écrit UN payload (create-or-update par nom) — appelée dans la
     * transaction du lot. Le rapport reçoit les avertissements d'écriture.
     *
     * @param array<string, mixed> $payload
     */
    abstract protected function apply(Connection $conn, array $payload, ImportReport $report): void;
}
