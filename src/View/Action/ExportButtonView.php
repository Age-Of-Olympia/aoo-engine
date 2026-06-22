<?php

namespace App\View\Action;

/**
 * Renders the admin "export" controls: a single-action download link and the
 * "export all" link. Both point at admin/action-export.php (a GET download), so
 * they stay plain anchors — no form/CSRF needed for a read-only export.
 */
final class ExportButtonView
{
    private const ENDPOINT = '/admin/action-export.php';

    public function single(int $actionId, string $label = 'Exporter'): string
    {
        return '<a class="btn btn-sm btn-outline-secondary" href="' . self::ENDPOINT . '?id=' . $actionId . '">'
            . $this->esc($label) . '</a>';
    }

    public function all(string $label = 'Exporter tout'): string
    {
        return '<a class="btn btn-sm btn-outline-secondary" href="' . self::ENDPOINT . '">'
            . $this->esc($label) . '</a>';
    }

    /**
     * "Export all" for a given object family (e.g. passive) — the endpoint routes
     * on ?type via the ExporterRegistry.
     */
    public function allOfType(string $objectType, string $label): string
    {
        return '<a class="btn btn-sm btn-outline-secondary" href="' . self::ENDPOINT . '?type=' . urlencode($objectType) . '">'
            . $this->esc($label) . '</a>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
