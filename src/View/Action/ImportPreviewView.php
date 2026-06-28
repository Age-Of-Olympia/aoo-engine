<?php

namespace App\View\Action;

use App\Service\ImportExport\ImportReport;

/**
 * Renders an {@see ImportReport} as the dry-run preview: what would be created,
 * updated, rejected or warned. The confirm button is only offered when there are
 * no rejections, because import is all-or-nothing — a single rejection would roll
 * the whole batch back.
 */
final class ImportPreviewView
{
    use EscapesHtml;

    public function render(ImportReport $report, string $filename, string $csrfTokenField, string $bundleHash = ''): string
    {
        $html = '<h1>Prévisualisation de l\'import</h1>';
        $html .= '<p class="wb-muted">Fichier : <code>' . $this->esc($filename) . '</code></p>';

        $html .= $this->nameSection('À créer', 'success', $report->created());
        $html .= $this->nameSection('À mettre à jour', 'info', $report->updated());
        $html .= $this->warningSection($report->warnings());
        $html .= $this->rejectionSection($report->rejected());

        if ($report->hasRejections()) {
            $html .= '<div class="alert alert-danger">Des objets sont rejetés : corrigez le bundle.'
                . ' L\'import étant tout-ou-rien, rien ne sera appliqué tant qu\'il reste un rejet.</div>';
            $html .= '<p><a class="btn btn-secondary" href="/admin/action-import.php">Choisir un autre fichier</a></p>';

            return $html;
        }

        // Bind the confirm to exactly the previewed bundle: commit re-hashes the
        // session JSON and refuses if it changed (other tab / re-upload).
        $html .= '<form method="post" action="/admin/action-import-commit.php" class="wb-form">'
            . $csrfTokenField
            . '<input type="hidden" name="bundle_hash" value="' . $this->esc($bundleHash) . '">'
            . '<div class="wb-form-actions">'
            . '<button type="submit" class="btn btn-success">Appliquer l\'import</button>'
            . '<a class="btn btn-secondary" href="/admin/action-import.php">Annuler</a>'
            . '</div>'
            . '</form>';

        return $html;
    }

    /**
     * @param array<int, string> $names
     */
    private function nameSection(string $title, string $badge, array $names): string
    {
        if ($names === []) {
            return '';
        }

        $items = '';
        foreach ($names as $name) {
            $items .= '<li><span class="badge badge-' . $badge . '">' . $this->esc($name) . '</span></li>';
        }

        return '<div class="wb-section-title">' . $this->esc($title) . ' (' . count($names) . ')</div>'
            . '<ul class="wb-list">' . $items . '</ul>';
    }

    /**
     * @param array<int, array{name: string, message: string}> $warnings
     */
    private function warningSection(array $warnings): string
    {
        if ($warnings === []) {
            return '';
        }

        $items = '';
        foreach ($warnings as $warning) {
            $items .= '<li><strong>' . $this->esc($warning['name']) . '</strong> — ' . $this->esc($warning['message']) . '</li>';
        }

        return '<div class="wb-section-title">Avertissements (' . count($warnings) . ')</div>'
            . '<ul class="wb-list wb-list--warning">' . $items . '</ul>';
    }

    /**
     * @param array<int, array{name: string, reason: string}> $rejections
     */
    private function rejectionSection(array $rejections): string
    {
        if ($rejections === []) {
            return '';
        }

        $items = '';
        foreach ($rejections as $rejection) {
            $items .= '<li><strong>' . $this->esc($rejection['name']) . '</strong> — ' . $this->esc($rejection['reason']) . '</li>';
        }

        return '<div class="wb-section-title">Rejets (' . count($rejections) . ')</div>'
            . '<ul class="wb-list wb-list--danger">' . $items . '</ul>';
    }

}
