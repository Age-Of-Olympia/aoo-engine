<?php

namespace App\Service\ImportExport;

/**
 * Resolves an {@see ObjectExporterInterface} by objectType. Defaults to the built-in set
 * (action, passive); tests inject their own. The seam that lets the export UI be
 * objectType-driven instead of hardcoded to actions.
 */
final class ExporterRegistry
{
    /** @var array<string, ObjectExporterInterface> */
    private array $exporters = [];

    /**
     * @param array<int, ObjectExporterInterface>|null $exporters null = the built-in set
     */
    public function __construct(?array $exporters = null)
    {
        foreach ($exporters ?? [new ActionExporter(), new PassiveExporter(), new ActionTypeConfigExporter(), new RaceExporter(), new FactionExporter(), new PlanExporter(), new DialogExporter(), new ItemExporter(), new RecipeExporter(), new EffectExporter()] as $exporter) {
            $this->register($exporter);
        }
    }

    public function register(ObjectExporterInterface $exporter): void
    {
        $this->exporters[$exporter->objectType()] = $exporter;
    }

    public function exporterFor(string $objectType): ?ObjectExporterInterface
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
