<?php

namespace Tests\Action\ImportExport;

use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ParsedBundle;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('import-export')]
class BundleEnvelopeParseTest extends TestCase
{
    public function testParsesAValidBundle(): void
    {
        $json = BundleEnvelope::encode(BundleEnvelope::build('action', [['name' => 'attaquer']]));

        $parsed = BundleEnvelope::parse($json);

        $this->assertInstanceOf(ParsedBundle::class, $parsed);
        $this->assertSame('action', $parsed->objectType);
        $this->assertSame([['name' => 'attaquer']], $parsed->objects);
    }

    public function testRoundTripsThroughBuildEncodeParse(): void
    {
        $bundle = BundleEnvelope::build('action', [['name' => 'a'], ['name' => 'b']]);

        $parsed = BundleEnvelope::parse(BundleEnvelope::encode($bundle));

        $this->assertSame($bundle['objects'], $parsed->objects);
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BundleEnvelope::parse('{not json');
    }

    public function testRejectsAWrongFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BundleEnvelope::parse(json_encode(['format' => 'something-else', 'formatVersion' => 1, 'objectType' => 'action', 'objects' => []]));
    }

    public function testRejectsAnUnsupportedFormatVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BundleEnvelope::parse(json_encode(['format' => 'aoo.config-bundle', 'formatVersion' => 999, 'objectType' => 'action', 'objects' => []]));
    }

    public function testRejectsAMissingObjectType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BundleEnvelope::parse(json_encode(['format' => 'aoo.config-bundle', 'formatVersion' => 1, 'objects' => []]));
    }

    public function testRejectsANonListObjectsField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BundleEnvelope::parse(json_encode(['format' => 'aoo.config-bundle', 'formatVersion' => 1, 'objectType' => 'action', 'objects' => ['x' => 1]]));
    }
}
