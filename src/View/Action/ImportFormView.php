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
        return '<form method="post" action="/admin/action-import.php" enctype="multipart/form-data" class="card">'
            . '<div class="card-body d-flex flex-wrap align-items-center gap-3">'
            . $csrfTokenField
            . '<input type="file" name="bundle" class="form-control-file" accept="application/json,.json" required>'
            . '<button type="submit" class="btn btn-primary">Prévisualiser l\'import</button>'
            . '</div>'
            . '</form>';
    }
}
