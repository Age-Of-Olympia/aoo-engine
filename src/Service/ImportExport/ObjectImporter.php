<?php

namespace App\Service\ImportExport;

/**
 * Imports a family of natural-key payloads (the counterpart of {@see ObjectExporter}).
 */
interface ObjectImporter
{
    /**
     * The bundle objectType this importer handles (e.g. "action").
     */
    public function objectType(): string;

    /**
     * Classify each object as create / update / reject / warn WITHOUT writing.
     *
     * @param array<int, mixed> $objects raw payloads from a parsed bundle
     */
    public function preview(array $objects): ImportReport;

    /**
     * Re-validate and apply the objects transactionally (all-or-nothing): if any
     * object is rejected, nothing is written. Returns the same report shape as
     * {@see preview()}, describing what was created/updated/rejected/warned.
     *
     * @param array<int, mixed> $objects raw payloads from a parsed bundle
     */
    public function import(array $objects): ImportReport;
}
