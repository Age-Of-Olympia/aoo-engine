<?php

namespace App\View\Action;

use App\Service\Action\Xp\XpCalculatorRegistry;

/**
 * The XP-rule editor for one action type, on the type-defaults page: a mode
 * select plus an integer input per knob of the current mode. Switching mode and
 * saving reloads with that mode's knobs. Posts to /admin/action-type-xp-save.php.
 */
final class TypeXpEditorView
{
    private XpCalculatorRegistry $calculators;

    /** Friendly labels for the param keys (fallback: the raw key). */
    private const LABELS = [
        'actorSuccess' => 'Acteur (succès)',
        'actorFail' => 'Acteur (échec)',
        'targetSuccess' => 'Cible (succès)',
        'targetFail' => 'Cible (échec)',
        'base' => 'Base',
        'min' => 'Minimum',
        'reducedXp' => 'XP réduit (même faction / inactif)',
        'diffCap' => 'Écart de rang max',
        'cap' => 'Plafond',
        'energieHighBonus' => "Bonus énergie (> 2)",
        'energieAnyBonus' => 'Bonus énergie (> 0)',
        'rankBonus' => 'Bonus rang inférieur',
    ];

    private const MODE_LABELS = [
        'fixed' => 'Fixe (récompense)',
        'attack' => 'Combat (rang/faction)',
        'steal' => 'Vol (plafonné)',
        'train' => 'Entraînement (énergie)',
    ];

    public function __construct(?XpCalculatorRegistry $calculators = null)
    {
        $this->calculators = $calculators ?? new XpCalculatorRegistry();
    }

    /**
     * @param array<string, int> $params the current mode's params
     */
    public function render(string $typeKey, string $mode, array $params, string $csrfTokenField): string
    {
        $modeOptions = '';
        foreach ($this->calculators->modes() as $value) {
            $sel = $value === $mode ? ' selected' : '';
            $modeOptions .= '<option value="' . $this->esc($value) . '"' . $sel . '>' . $this->esc(self::MODE_LABELS[$value] ?? $value) . '</option>';
        }

        $fields = '';
        foreach ($params as $key => $value) {
            $label = self::LABELS[$key] ?? $key;
            $fields .= '<label class="wb-field"><span>' . $this->esc($label) . '</span>'
                . '<input class="form-control" type="number" name="params[' . $this->esc($key) . ']" value="' . (int) $value . '"></label>';
        }

        return '<form method="post" action="/admin/action-type-xp-save.php" class="wb-form wb-xp">'
            . $csrfTokenField
            . '<input type="hidden" name="type_key" value="' . $this->esc($typeKey) . '">'
            . '<div class="wb-section-title">Expérience « ' . $this->esc($typeKey) . ' »</div>'
            . '<label class="wb-field"><span>Mode</span><select class="form-control" name="mode">' . $modeOptions . '</select></label>'
            . '<p class="wb-muted">Changer le mode puis enregistrer affiche ses paramètres. Les algorithmes (combat/vol/entraînement) restent dans le code ; seules leurs constantes sont éditables ici.</p>'
            . '<div class="wb-grid">' . $fields . '</div>'
            . '<div class="wb-form-actions"><button type="submit" class="btn btn-success">Enregistrer l\'XP</button></div>'
            . '</form>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
