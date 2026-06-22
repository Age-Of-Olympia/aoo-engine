<?php

namespace App\Service\ImportExport;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The portable wrapper around an array of exported objects. One bundle carries a
 * single `objectType`; `objects` is always an array (a single object is an array
 * of one). Building and JSON-encoding live here so every exporter emits the same
 * envelope and the same diff-friendly formatting.
 */
final class BundleEnvelope
{
    public const FORMAT = 'aoo.config-bundle';
    public const FORMAT_VERSION = 1;

    /**
     * @param array<int, array<string, mixed>> $objects
     *
     * @return array<string, mixed>
     */
    public static function build(string $objectType, array $objects, ?DateTimeImmutable $exportedAt = null): array
    {
        $exportedAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return [
            'format' => self::FORMAT,
            'formatVersion' => self::FORMAT_VERSION,
            'exportedAt' => $exportedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'objectType' => $objectType,
            'objects' => array_values($objects),
        ];
    }

    /**
     * Pretty-print a bundle as diffable JSON: unescaped unicode and slashes so
     * accents and paths stay human-readable across re-exports.
     *
     * @param array<string, mixed> $bundle
     */
    public static function encode(array $bundle): string
    {
        return json_encode(
            $bundle,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
