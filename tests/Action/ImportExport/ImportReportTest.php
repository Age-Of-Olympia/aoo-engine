<?php

namespace Tests\Action\ImportExport;

use App\Service\ImportExport\ImportReport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class ImportReportTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $report = new ImportReport();

        $this->assertSame([], $report->created());
        $this->assertSame([], $report->updated());
        $this->assertSame([], $report->rejected());
        $this->assertSame([], $report->warnings());
        $this->assertFalse($report->hasRejections());
    }

    public function testCollectsEachClassification(): void
    {
        $report = new ImportReport();
        $report->addCreated('a');
        $report->addUpdated('b');
        $report->reject('c', 'nope');
        $report->warn('d', 'careful');

        $this->assertSame(['a'], $report->created());
        $this->assertSame(['b'], $report->updated());
        $this->assertSame([['name' => 'c', 'reason' => 'nope']], $report->rejected());
        $this->assertSame([['name' => 'd', 'message' => 'careful']], $report->warnings());
        $this->assertTrue($report->hasRejections());
    }
}
