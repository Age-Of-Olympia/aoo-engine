<?php

namespace App\Database;

/**
 * Rows of a cached SELECT (Db::exeCached), read like the mysqli_result they
 * replace. Every fetch hands out a fresh copy: a caller that edits its rows
 * cannot change what the next caller reads.
 */
final class CachedResult
{
    public readonly int $num_rows;
    private int $position = 0;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows)
    {
        $this->num_rows = count($rows);
    }

    /** @return array<string, mixed>|null */
    public function fetch_assoc(): ?array
    {
        return $this->rows[$this->position++] ?? null;
    }

    public function fetch_object(): ?object
    {
        $row = $this->fetch_assoc();

        return $row === null ? null : (object) $row;
    }

    /** @return list<array<string, mixed>> */
    public function fetch_all(): array
    {
        return $this->rows;
    }
}
