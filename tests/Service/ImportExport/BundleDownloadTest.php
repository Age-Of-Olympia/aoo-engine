<?php

namespace Tests\Service\ImportExport;

use App\Service\ImportExport\BundleDownload;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class BundleDownloadTest extends TestCase
{
    public function testFullExportFilenameUsesABundleSuffix(): void
    {
        $this->assertSame('action-bundle.json', BundleDownload::filename('action'));
    }

    public function testSingleExportFilenameCombinesTypeAndName(): void
    {
        $this->assertSame('action-attaquer.json', BundleDownload::filename('action', 'attaquer'));
    }

    public function testNameIsLowercasedAndKeepsSlugCharacters(): void
    {
        $this->assertSame('action-attaque_double.json', BundleDownload::filename('action', 'Attaque_Double'));
    }

    public function testUnsafeCharactersIncludingAccentsCollapseToDashes(): void
    {
        $this->assertSame('action-coup-d-p-e.json', BundleDownload::filename('action', 'Coup d\'épée'));
    }

    public function testHeaderInjectionCharactersCannotLeakIntoTheFilename(): void
    {
        $filename = BundleDownload::filename('action', "evil\"\r\nContent-Type: x");

        $this->assertSame('action-evil-content-type-x.json', $filename);
        $this->assertStringNotContainsString('"', $filename);
        $this->assertStringNotContainsString("\n", $filename);
        $this->assertStringNotContainsString("\r", $filename);
    }

    public function testEmptyNameFallsBackInsteadOfProducingABareDot(): void
    {
        $this->assertSame('action-unnamed.json', BundleDownload::filename('action', '***'));
    }
}
