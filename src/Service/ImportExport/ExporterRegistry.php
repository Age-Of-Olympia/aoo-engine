<?php

namespace App\Service\ImportExport;

/**
 * Resolves an {@see ObjectExporter} by objectType. Defaults to the built-in set
 * (action, passive); tests inject their own. The seam that lets the export UI be
 * objectType-driven instead of hardcoded to actions.
 */
final class ExporterRegistry
{
    /** @var array<string, ObjectExporter> */
    private array $exporters = [];

    /**
     * @param array<int, ObjectExporter>|null $exporters null = the built-in set
     */
    public function __construct(?array $exporters = null)
    {
        foreach ($exporters ?? [new ActionExporter(), new PassiveExporter(), new ActionTypeConfigExporter()] as $exporter) {
            $this->register($exporter);
        }
    }

    public function register(ObjectExporter $exporter): void
    {
        $this->exporters[$exporter->objectType()] = $exporter;
    }

    public function exporterFor(string $objectType): ?ObjectExporter
    {
        return $this->exporters[$objectType] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function objectTypes(): array
    {
        return array_keys($this->exporters);
    }
}
