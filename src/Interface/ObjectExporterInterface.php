<?php

namespace App\Interface;

/**
 * Turns one family of domain entities into a natural-key payload array suitable
 * for a {@see BundleEnvelope}. Implementations never emit DB ids — identity is
 * carried by natural keys so a bundle is portable across environments.
 */
interface ObjectExporterInterface
{
    /**
     * The bundle's `objectType` discriminator (e.g. "action").
     */
    public function objectType(): string;

    /**
     * Serialise a single entity to its natural-key payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array;

    /**
     * Serialise every entity of this type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportAll(): array;
}
