<?php

namespace Tests\Action\ImportExport;

use App\View\Action\ExportButtonView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ExportButtonViewTest extends TestCase
{
    public function testSingleLinksToTheActionExportEndpointWithTheId(): void
    {
        $html = (new ExportButtonView())->single(42);

        $this->assertStringContainsString('href="/admin/action-export.php?type=action&id=42"', $html);
        $this->assertStringContainsString('>Exporter</a>', $html);
    }

    public function testSingleOfTypeRoutesOnTheObjectFamily(): void
    {
        $html = (new ExportButtonView())->singleOfType('passive', 7);

        $this->assertStringContainsString('href="/admin/action-export.php?type=passive&id=7"', $html);
        $this->assertStringContainsString('>Exporter</a>', $html);
    }

    public function testAllLinksToTheBareExportEndpoint(): void
    {
        $html = (new ExportButtonView())->all();

        $this->assertStringContainsString('href="/admin/action-export.php"', $html);
        $this->assertStringContainsString('>Exporter tout</a>', $html);
    }

    public function testLabelIsHtmlEscaped(): void
    {
        $html = (new ExportButtonView())->single(1, '<x>"&');

        $this->assertStringContainsString('&lt;x&gt;&quot;&amp;', $html);
        $this->assertStringNotContainsString('<x>"&<', $html);
    }
}
