<?php

namespace App\Service\ImportExport;

/**
 * The validated result of {@see BundleEnvelope::parse()}: the bundle's object
 * type plus its raw object payloads (a list, each entry still untrusted — the
 * per-object importer validates them).
 */
final class ParsedBundle
{
    /**
     * @param array<int, mixed> $objects
     */
    public function __construct(
        public readonly string $objectType,
        public readonly array $objects,
    ) {
    }
}
