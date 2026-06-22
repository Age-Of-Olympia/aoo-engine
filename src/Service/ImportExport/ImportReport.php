<?php

namespace App\Service\ImportExport;

/**
 * Collects the outcome of an import preview/commit, classified per object:
 * created, updated, rejected (with a reason) or accepted-with-warnings (e.g. an
 * unknown race link skipped). Shared by preview (no writes) and commit so both
 * speak the same shape to the UI.
 */
final class ImportReport
{
    /** @var array<int, string> */
    private array $created = [];

    /** @var array<int, string> */
    private array $updated = [];

    /** @var array<int, array{name: string, reason: string}> */
    private array $rejected = [];

    /** @var array<int, array{name: string, message: string}> */
    private array $warnings = [];

    public function addCreated(string $name): void
    {
        $this->created[] = $name;
    }

    public function addUpdated(string $name): void
    {
        $this->updated[] = $name;
    }

    public function reject(string $name, string $reason): void
    {
        $this->rejected[] = ['name' => $name, 'reason' => $reason];
    }

    public function warn(string $name, string $message): void
    {
        $this->warnings[] = ['name' => $name, 'message' => $message];
    }

    /** @return array<int, string> */
    public function created(): array
    {
        return $this->created;
    }

    /** @return array<int, string> */
    public function updated(): array
    {
        return $this->updated;
    }

    /** @return array<int, array{name: string, reason: string}> */
    public function rejected(): array
    {
        return $this->rejected;
    }

    /** @return array<int, array{name: string, message: string}> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasRejections(): bool
    {
        return $this->rejected !== [];
    }
}
