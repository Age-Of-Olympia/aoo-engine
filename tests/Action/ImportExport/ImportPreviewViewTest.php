<?php

namespace Tests\Action\ImportExport;

use App\Service\ImportExport\ImportReport;
use App\View\Action\ImportFormView;
use App\View\Action\ImportPreviewView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ImportPreviewViewTest extends TestCase
{
    private const TOKEN = '<input type="hidden" name="csrf_token" value="t">';

    public function testCleanPreviewOffersTheConfirmButton(): void
    {
        $report = new ImportReport();
        $report->addCreated('attaquer');
        $report->addUpdated('courir');
        $report->warn('prier', 'Race inconnue ignorée : « Atlante ».');

        $html = (new ImportPreviewView())->render($report, 'bundle.json', self::TOKEN, 'abc123');

        $this->assertStringContainsString('bundle.json', $html);
        $this->assertStringContainsString('attaquer', $html);
        $this->assertStringContainsString('courir', $html);
        $this->assertStringContainsString('Atlante', $html);
        $this->assertStringContainsString('action="/admin/action-import-commit.php"', $html);
        $this->assertStringContainsString('Appliquer l\'import', $html);
        $this->assertStringContainsString('name="bundle_hash" value="abc123"', $html);
        $this->assertStringContainsString(self::TOKEN, $html);
    }

    public function testRejectionsHideTheConfirmButton(): void
    {
        $report = new ImportReport();
        $report->addCreated('attaquer');
        $report->reject('bogus', 'Type d\'action inconnu : « x ».');

        $html = (new ImportPreviewView())->render($report, 'bundle.json', self::TOKEN);

        $this->assertStringContainsString('bogus', $html);
        $this->assertStringNotContainsString('action="/admin/action-import-commit.php"', $html);
        $this->assertStringNotContainsString('Appliquer l\'import', $html);
    }

    public function testFilenameAndReportContentAreHtmlEscaped(): void
    {
        $report = new ImportReport();
        $report->reject('<x>', '<script>alert(1)</script>');

        $html = (new ImportPreviewView())->render($report, '<evil>.json', self::TOKEN);

        $this->assertStringContainsString('&lt;x&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;evil&gt;.json', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testImportFormPostsMultipartToTheImportEndpoint(): void
    {
        $html = (new ImportFormView())->render(self::TOKEN);

        $this->assertStringContainsString('action="/admin/action-import.php"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept="application/json,.json"', $html);
        $this->assertStringContainsString(self::TOKEN, $html);
    }
}
