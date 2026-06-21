<?php

namespace App\View\Action;

use App\Action\OutcomeInstruction\OutcomeInstructionFactory;
use App\Entity\OutcomeInstruction;

/**
 * Read-only view of an action's automatic outcome instructions — the ones added
 * in code (e.g. AttackAction's adrenaline) rather than configured in the DB.
 * They can't be edited here; this just makes the full behaviour visible so the
 * Configurer isn't misleadingly incomplete. Returns '' when there are none.
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
                . ' <span class="badge wb-auto-badge">auto · code</span></div>'
                . '<div class="wb-block-body">' . ($rows !== '' ? $rows : '<span class="wb-muted">—</span>') . '</div>'
                . '</div>';
        }

        if ($blocks === '') {
            return '';
        }

        return '<div class="wb-section-title">Automatiques <span class="wb-muted">(définies dans le code, lecture seule)</span></div>'
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
