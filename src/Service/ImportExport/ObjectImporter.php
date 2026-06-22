<?php

namespace App\Service\ImportExport;

/**
 * Imports a family of natural-key payloads (the counterpart of {@see ObjectExporter}).
 *
 * For now the contract is the dry-run {@see preview()} — the transactional
 * commit (import()) lands in a later slice once its writes are security-reviewed.
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
}
