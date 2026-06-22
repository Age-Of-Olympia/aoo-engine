<?php

namespace App\View\Action;

use App\Entity\ActionPassive;

/**
 * The passive workbench body: a filterable list of passives on the left and the
 * edit form for the selected one on the right. Reuses the action workbench's
 * wb-* layout/CSS. Traits are edited as a comma-separated list, conditions as a
 * JSON blob.
 */
final class PassiveWorkbenchView
{
    private const TYPES = ['att' => 'Attaque', 'def' => 'Défense', 'mixte' => 'Mixte'];
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
            $list .= '<a class="wb-item' . $active . '" href="/admin/passive-workbench.php?id=' . (int) $passive->getId() . '"'
                . ' data-search="' . $this->esc($search) . '">'
                . '<span class="wb-item-name">' . $this->esc($passive->getDisplayName()) . '</span>'
                . '<span class="wb-item-meta">' . $this->esc($passive->getType()) . ' · niv.' . (int) $passive->getLevel()
                . ' · ' . $this->esc($passive->getRace()) . '</span>'
                . '</a>';
        }

        $form = $selected === null
            ? '<p class="wb-empty">Sélectionnez un passif.</p>'
            : $this->form($selected, $csrfTokenField);

        return '<div class="wb">'
            . '<div class="wb-col"><div class="wb-col-head">Passifs <small>' . count($passives) . '</small></div>'
            . '<div class="wb-col-body">'
            . '<input type="text" class="wb-search" id="wb-search" placeholder="Filtrer…" autocomplete="off">'
            . '<div class="wb-list" id="wb-list">' . $list . '</div></div></div>'
            . '<div class="wb-col wb-col--wide"><div class="wb-col-body">' . $form . '</div></div>'
            . '</div>';
    }

    private function form(ActionPassive $passive, string $csrfTokenField): string
    {
        $conditions = $passive->getConditions();
        $conditionsJson = $conditions === null ? '' : (string) json_encode($conditions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<form method="post" action="/admin/passive-save.php" class="wb-form">'
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
            . $this->textarea('conditions', 'Conditions (JSON)', $conditionsJson)
            . '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer</button></div>'
            . '</form>';
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
