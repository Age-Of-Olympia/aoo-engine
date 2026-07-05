<?php

namespace App\View\Action;

/**
 * The bundle upload form for admin/action-import.php. A plain multipart POST to
 * the same page; the controller validates the file, parses it and redirects to
 * the preview. Markup only — no logic.
 */
final class ImportFormView
{
    public function render(string $csrfTokenField): string
    {
        return '<form method="post" action="/admin/action-import.php" enctype="multipart/form-data" class="wb-form">'
            . $csrfTokenField
            . '<p class="wb-muted">Importez un bundle <code>aoo.config-bundle</code> (.json) exporté depuis cette page.'
            . ' L\'import est transactionnel : en cas de rejet, rien n\'est appliqué.</p>'
            . '<div class="wb-form-actions">'
            . '<input type="file" name="bundle" accept="application/json,.json" required>'
            . '<button type="submit" class="btn btn-primary">Prévisualiser l\'import</button>'
            . '</div>'
            . '</form>';
    }
}
