<?php

namespace Tests\Service\ImportExport;

use App\Service\ImportExport\BundleEnvelope;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class BundleEnvelopeTest extends TestCase
{
    public function testBuildWrapsObjectsWithFormatMetadata(): void
    {
        $bundle = BundleEnvelope::build(
            'action',
            [['name' => 'attaquer']],
            new DateTimeImmutable('2026-06-22T10:00:00', new DateTimeZone('UTC'))
        );

        $this->assertSame([
            'format' => 'aoo.config-bundle',
            'formatVersion' => 1,
            'exportedAt' => '2026-06-22T10:00:00Z',
            'objectType' => 'action',
            'objects' => [['name' => 'attaquer']],
        ], $bundle);
    }

    public function testBuildNormalisesTheTimestampToUtc(): void
    {
        $bundle = BundleEnvelope::build(
            'action',
            [],
            new DateTimeImmutable('2026-06-22T12:00:00', new DateTimeZone('Europe/Paris'))
        );

        $this->assertSame('2026-06-22T10:00:00Z', $bundle['exportedAt']);
    }

    public function testObjectsIsAlwaysAReindexedArray(): void
    {
        $bundle = BundleEnvelope::build('action', [5 => ['name' => 'a'], 9 => ['name' => 'b']]);

        $this->assertSame([['name' => 'a'], ['name' => 'b']], $bundle['objects']);
    }

    public function testEncodeProducesDiffableUnescapedJson(): void
    {
        $json = BundleEnvelope::encode([
            'objectType' => 'action',
            'objects' => [['text' => 'corps à corps', 'icon' => 'img/a.png']],
        ]);

        $this->assertStringContainsString('corps à corps', $json);
        $this->assertStringContainsString('img/a.png', $json);
        $this->assertStringContainsString("\n", $json);
        $this->assertStringNotContainsString('\\/', $json);
    }
}
