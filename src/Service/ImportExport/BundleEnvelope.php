<?php

namespace App\Service\ImportExport;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

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

    /**
     * Decode and validate a bundle's JSON, returning its objectType + objects.
     * Fails closed with a clear message on anything malformed: bad JSON, wrong
     * format/version, missing objectType, or a non-list objects field. The depth
     * limit bounds nesting so a hostile upload can't exhaust the parser.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $json): ParsedBundle
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('JSON invalide : ' . $exception->getMessage());
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Le bundle doit être un objet JSON.');
        }
        if (($decoded['format'] ?? null) !== self::FORMAT) {
            throw new InvalidArgumentException('Format de bundle inconnu.');
        }
        if (($decoded['formatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Version de format non supportée.');
        }

        $objectType = $decoded['objectType'] ?? null;
        if (!is_string($objectType) || $objectType === '') {
            throw new InvalidArgumentException('objectType manquant dans le bundle.');
        }

        $objects = $decoded['objects'] ?? null;
        if (!is_array($objects) || !array_is_list($objects)) {
            throw new InvalidArgumentException('Le champ « objects » doit être une liste.');
        }

        return new ParsedBundle($objectType, $objects);
    }
}
