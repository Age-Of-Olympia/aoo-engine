<?php

namespace App\View\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\OutcomeInstruction;

/**
 * Read-only view of the outcome instructions an action inherits from its TYPE
 * (e.g. an attack's adrenaline), resolved via ActionTypeInstructionResolver.
 * They're configured on the type, not this action, so they're shown read-only
 * here; the type-defaults editor is where they're changed. Returns '' when
 * there are none.
 */
final class AutomaticOutcomesView
{
    /**
     * @param iterable<OutcomeInstruction> $instructions
     */
    public function render(iterable $instructions): string
    {
        $blocks = '';
        foreach ($instructions as $instruction) {
            $type = OutcomeInstructionFactory::typeOf($instruction);
            $params = $instruction->getParameters() ?? [];
            $rows = '';
            foreach ($params as $key => $value) {
                $rows .= '<div class="wb-auto-row"><code>' . $this->esc((string) $key) . '</code> '
                    . $this->esc($this->format($value)) . '</div>';
            }
            $blocks .= '<div class="wb-block wb-block--auto">'
                . '<div class="wb-block-head">' . $this->esc($type)
                . ' <span class="badge wb-auto-badge">hérité du type</span></div>'
                . '<div class="wb-block-body">' . ($rows !== '' ? $rows : '<span class="wb-muted">—</span>') . '</div>'
                . '</div>';
        }

        if ($blocks === '') {
            return '';
        }

        return '<div class="wb-section-title">Héritées du type <span class="wb-muted">(définies sur le type d\'action, lecture seule)</span></div>'
            . '<div class="wb-grid">' . $blocks . '</div>';
    }

    private function format(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
