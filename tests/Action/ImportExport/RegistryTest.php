<?php

namespace Tests\Action\ImportExport;

use App\Service\ImportExport\ExporterRegistry;
use App\Service\ImportExport\ImportReport;
use App\Service\ImportExport\ImporterRegistry;
use App\Interface\ObjectExporterInterface;
use App\Interface\ObjectImporterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class RegistryTest extends TestCase
{
    public function testExporterRegistryResolvesByObjectType(): void
    {
        $registry = new ExporterRegistry([$this->exporter('action'), $this->exporter('passive')]);

        $this->assertSame('action', $registry->exporterFor('action')?->objectType());
        $this->assertSame('passive', $registry->exporterFor('passive')?->objectType());
        $this->assertNull($registry->exporterFor('unknown'));
        $this->assertSame(['action', 'passive'], $registry->objectTypes());
    }

    public function testImporterRegistryResolvesByObjectType(): void
    {
        $registry = new ImporterRegistry([$this->importer('action'), $this->importer('passive')]);

        $this->assertSame('passive', $registry->importerFor('passive')?->objectType());
        $this->assertNull($registry->importerFor('nope'));
        $this->assertSame(['action', 'passive'], $registry->objectTypes());
    }

    public function testTheBuiltInExporterRegistryKnowsEveryBundledType(): void
    {
        // Default exporters are lazy (no DB at construction), so this is safe here.
        $this->assertSame(
            ['action', 'passive', 'action-type', 'race', 'faction', 'plan', 'dialog', 'item', 'recipe', 'effect'],
            (new ExporterRegistry())->objectTypes()
        );
    }

    public function testRegisterOverridesAnExistingType(): void
    {
        $registry = new ExporterRegistry([$this->exporter('action')]);
        $replacement = $this->exporter('action');
        $registry->register($replacement);

        $this->assertSame($replacement, $registry->exporterFor('action'));
    }

    private function exporter(string $type): ObjectExporterInterface
    {
        return new class ($type) implements ObjectExporterInterface {
            public function __construct(private string $type)
            {
            }

            public function objectType(): string
            {
                return $this->type;
            }

            public function toArray(object $entity): array
            {
                return [];
            }

            public function exportAll(): array
            {
                return [];
            }
        };
    }

    private function importer(string $type): ObjectImporterInterface
    {
        return new class ($type) implements ObjectImporterInterface {
            public function __construct(private string $type)
            {
            }

            public function objectType(): string
            {
                return $this->type;
            }

            public function preview(array $objects): ImportReport
            {
                return new ImportReport();
            }

            public function import(array $objects): ImportReport
            {
                return new ImportReport();
            }
        };
    }
}
