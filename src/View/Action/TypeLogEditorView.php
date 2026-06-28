<?php

namespace App\View\Action;

/**
 * The log-template editor for one action type, shown on the type-defaults page.
 * Two templates (actor / target line) with the supported placeholders. Posts to
 * /admin/action-type-log-save.php.
 */
final class TypeLogEditorView
{
    use EscapesHtml;
    use RendersInheritanceBanner;

    public function render(string $typeKey, ?string $actorTemplate, ?string $targetTemplate, string $csrfTokenField, ?string $inheritedFrom = null, ?string $overriddenParent = null): string
    {
        return '<form method="post" action="/admin/action-type-log-save.php" class="wb-form wb-logs">'
            . $csrfTokenField
            . '<input type="hidden" name="type_key" value="' . $this->esc($typeKey) . '">'
            . '<div class="wb-section-title">Messages de journal « ' . $this->esc($typeKey) . ' »</div>'
            . $this->inheritanceBanner($typeKey, $inheritedFrom, $overriddenParent)
            . '<p class="wb-muted">Placeholders : <code>{actor}</code>, <code>{target}</code>, <code>{action}</code> '
            . '(nom affiché), <code>{weapon}</code> (« avec &lt;arme&gt; », vide pour les animaux). '
            . 'Laisser vide pour aucune ligne. Hérité par les types enfants sans message propre.</p>'
            . '<label class="wb-field wb-field--wide"><span>Ligne acteur</span>'
            . '<textarea class="form-control" name="actor_template" rows="2">' . $this->esc((string) $actorTemplate) . '</textarea></label>'
            . '<label class="wb-field wb-field--wide"><span>Ligne cible</span>'
            . '<textarea class="form-control" name="target_template" rows="2">' . $this->esc((string) $targetTemplate) . '</textarea></label>'
            . '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer les messages</button></div>'
            . '</form>';
    }
}
