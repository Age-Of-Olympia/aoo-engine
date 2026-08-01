<?php

namespace App\Service\ImportExport;

/**
 * Resolves an {@see ObjectImporterInterface} by objectType. Defaults to the built-in set
 * (action, passive); tests inject their own. Lets the import flow route a bundle
 * to the right importer from its `objectType`, instead of hardcoding actions.
 */
final class ImporterRegistry
{
    /** @var array<string, ObjectImporterInterface> */
    private array $importers = [];

    /**
     * @param array<int, ObjectImporterInterface>|null $importers null = the built-in set
     */
    public function __construct(?array $importers = null)
    {
        foreach ($importers ?? [new ActionImporter(), new PassiveImporter(), new ActionTypeConfigImporter(), new RaceImporter(), new FactionImporter(), new PlanImporter(), new DialogImporter(), new ItemImporter(), new RecipeImporter(), new EffectImporter()] as $importer) {
            $this->register($importer);
        }
    }

    public function register(ObjectImporterInterface $importer): void
    {
        $this->importers[$importer->objectType()] = $importer;
    }

    public function importerFor(string $objectType): ?ObjectImporterInterface
    {
        return $this->importers[$objectType] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function objectTypes(): array
    {
        return array_keys($this->importers);
    }
}
