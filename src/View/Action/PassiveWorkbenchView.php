<?php

namespace App\View\Action;

use App\Entity\ActionPassive;

/**
 * The passive workbench body: a filterable list of passives on the left and the
 * edit form for the selected one on the right. Reuses the action workbench's
 * wb-* layout/CSS. Traits are edited as a comma-separated list; conditions use
 * the structured {@see PassiveConditionEditorView} (weapon/category picker with a
 * raw-JSON fallback).
 */
final class PassiveWorkbenchView
{
    private const TYPES = ['att' => 'Attaque', 'def' => 'Défense', 'mixte' => 'Mixte'];
    /** Passives have no stored icon; fall back to a glyph per type for the list / folded rail. */
    private const TYPE_ICONS = ['att' => 'ra-sword', 'def' => 'ra-shield', 'mixte' => 'ra-crossed-swords'];
    private const CATEGORIES = [
        '' => '—', 'melee' => 'Mêlée', 'distance' => 'Distance',
        'magic' => 'Magie', 'stealth' => 'Furtivité', 'survival' => 'Survie',
    ];

    /**
     * @param array<int, ActionPassive> $passives
     */
    public function render(array $passives, ?ActionPassive $selected, string $csrfTokenField): string
    {
        $list = '';
        foreach ($passives as $passive) {
            $active = ($selected !== null && $passive->getId() === $selected->getId()) ? ' wb-item--active' : '';
            $search = strtolower($passive->getName() . ' ' . $passive->getDisplayName() . ' ' . ($passive->getCategory() ?? ''));
            $icon = self::TYPE_ICONS[$passive->getType()] ?? 'ra-aura';
            // Same item structure as the action list (icon + wb-item-text) so the
            // folded rail shows the icon and hides the text, instead of breaking.
            $list .= '<a class="wb-item' . $active . '" href="/admin/passive-workbench.php?id=' . (int) $passive->getId() . '"'
                . ' title="' . $this->esc($passive->getDisplayName()) . '"'
                . ' data-search="' . $this->esc($search) . '">'
                . '<i class="ra ' . $icon . ' wb-item-icon"></i>'
                . '<span class="wb-item-text">'
                . '<span class="wb-item-name">' . $this->esc($passive->getDisplayName()) . '</span>'
                . '<span class="wb-item-meta">' . $this->esc($passive->getType()) . ' · niv.' . (int) $passive->getLevel()
                . ' · ' . $this->esc($passive->getRace()) . '</span>'
                . '</span>'
                . '</a>';
        }

        $form = $selected === null
            ? '<p class="wb-empty">Sélectionnez un passif.</p>'
            : $this->form($selected, $csrfTokenField);
        $name = $selected !== null ? $this->esc($selected->getDisplayName()) : '';

        $listBody = (new WorkbenchListHeaderView())->render($this->createForm($csrfTokenField), 'passive', 'Exporter tout')
            . '<input type="text" class="wb-search" id="wb-search" placeholder="Filtrer…" autocomplete="off">'
            . '<div class="wb-list" id="wb-list">' . $list . '</div>';
        $editorHead = '<span class="wb-col-head-title">Configurer</span><small>' . $name . '</small>';

        // Same shell as the action workbench (no mirroring) — only the list/editor
        // contents differ.
        return (new WorkbenchLayoutView())->render('Passifs', count($passives), $listBody, $editorHead, $form);
    }

    private function form(ActionPassive $passive, string $csrfTokenField): string
    {
        return '<form method="post" action="/admin/passive-save.php" id="wb-passive-form" class="wb-form">'
            . $csrfTokenField
            . '<input type="hidden" name="passive_id" value="' . (int) $passive->getId() . '">'
            . '<code class="wb-chip">' . $this->esc($passive->getName()) . '</code>'
            . '<div class="wb-grid">'
            . $this->input('name', 'Nom (clé)', $passive->getName())
            . $this->input('displayName', 'Nom affiché', $passive->getDisplayName())
            . $this->select('type', 'Type', self::TYPES, $passive->getType())
            . $this->input('carac', 'Carac', $passive->getCarac())
            . $this->input('value', 'Valeur', (string) $passive->getValue(), 'number', '0.01')
            . $this->input('level', 'Niveau', (string) $passive->getLevel(), 'number')
            . $this->input('race', 'Race', $passive->getRace())
            . $this->select('category', 'Catégorie', self::CATEGORIES, (string) ($passive->getCategory() ?? ''))
            . $this->input('prerequisites', 'Prérequis', (string) ($passive->getPrerequisites() ?? ''))
            . $this->input('traits', 'Traits (séparés par des virgules)', implode(', ', $passive->getTraits()))
            . '</div>'
            . $this->textarea('text', 'Texte', (string) $passive->getText())
            . (new PassiveConditionEditorView())->render($passive->getConditions())
            . '</form>'
            // Sibling delete form; its button lives in the shared footer (form= attr).
            . '<form method="post" action="/admin/passive-delete.php" id="wb-passive-delete-form" class="wb-delete-form"'
            . ' onsubmit="return confirm(\'Supprimer définitivement ce passif ?\');">'
            . $csrfTokenField
            . '<input type="hidden" name="passive_id" value="' . (int) $passive->getId() . '">'
            . '</form>'
            . (new WorkbenchFooterView())->render(
                'wb-passive-form',
                'wb-passive-delete-form',
                'Supprimer le passif',
                (new ExportButtonView())->singleOfType('passive', (int) $passive->getId()),
            );
    }

    private function createForm(string $csrfTokenField): string
    {
        return '<details class="wb-create"><summary class="btn btn-sm btn-success">+ Nouveau passif</summary>'
            . '<form method="post" action="/admin/passive-create.php" class="wb-create-form">'
            . $csrfTokenField
            . '<input class="form-control" type="text" name="name" placeholder="nom (clé)" required autocomplete="off">'
            . '<button type="submit" class="btn btn-sm btn-success">Créer</button>'
            . '</form></details>';
    }

    private function input(string $name, string $label, string $value, string $type = 'text', ?string $step = null): string
    {
        $stepAttr = $step !== null ? ' step="' . $this->esc($step) . '"' : '';

        return '<label class="wb-field"><span>' . $this->esc($label) . '</span>'
            . '<input class="form-control" type="' . $this->esc($type) . '"' . $stepAttr
            . ' name="passive[' . $this->esc($name) . ']" value="' . $this->esc($value) . '" autocomplete="off"></label>';
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, string $label, array $options, string $current): string
    {
        $opts = '';
        foreach ($options as $value => $optLabel) {
            $sel = $value === $current ? ' selected' : '';
            $opts .= '<option value="' . $this->esc($value) . '"' . $sel . '>' . $this->esc($optLabel) . '</option>';
        }

        return '<label class="wb-field"><span>' . $this->esc($label) . '</span>'
            . '<select class="form-control" name="passive[' . $this->esc($name) . ']">' . $opts . '</select></label>';
    }

    private function textarea(string $name, string $label, string $value): string
    {
        return '<label class="wb-field wb-field--wide"><span>' . $this->esc($label) . '</span>'
            . '<textarea class="form-control" name="passive[' . $this->esc($name) . ']" rows="3">' . $this->esc($value) . '</textarea></label>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
