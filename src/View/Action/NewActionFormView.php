<?php

namespace App\View\Action;

/**
 * The "new action" form shown in the workbench actions column. Lives here so the
 * page controller doesn't build form HTML inline.
 */
final class NewActionFormView
{
    /**
     * @param array<string, string> $types discriminator => label
     */
    public function render(array $types, string $csrfTokenField): string
    {
        $options = '';
        foreach ($types as $value => $label) {
            $options .= '<option value="' . $this->esc($value) . '">' . $this->esc($label) . '</option>';
        }

        return '<details class="wb-create"><summary class="btn btn-sm btn-success">+ Nouvelle action</summary>'
            . '<form method="post" action="/admin/action-create.php" class="wb-create-form">'
            . $csrfTokenField
            . '<select class="form-control" name="type">' . $options . '</select>'
            . '<input class="form-control" type="text" name="name" placeholder="nom (clé)" required autocomplete="off">'
            . '<input class="form-control" type="text" name="display_name" placeholder="nom affiché" autocomplete="off">'
            . '<div class="wb-create-row">'
            . '<input class="form-control" type="number" name="level" value="1" min="1" title="niveau">'
            . '<input class="form-control" type="text" name="category" placeholder="catégorie" autocomplete="off">'
            . '</div>'
            . '<button type="submit" class="btn btn-sm btn-success">Créer</button>'
            . '</form></details>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
