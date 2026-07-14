<?php

namespace App\Action\Combat;

/**
 * Jet résolu avec avantage / désavantage : jet retenu, plus la somme retenue et
 * la somme écartée pour tracer le détail dans les logs.
 */
final class AdvantageRoll
{
    public const MODE_ADVANTAGE = 'advantage';
    public const MODE_DISADVANTAGE = 'disadvantage';

    /**
     * @param array<int, int> $roll
     */
    public function __construct(
        public readonly array $roll,
        public readonly ?string $mode = null,
        public readonly int $keptSum = 0,
        public readonly ?int $discardedSum = null,
    ) {
    }

    public function isModified(): bool
    {
        return $this->mode !== null && $this->discardedSum !== null;
    }

    /** Ex : "Avantage : jets 12 et 7, 12 retenu" ; vide si rien n'a joué. */
    public function describe(): string
    {
        if (!$this->isModified()) {
            return '';
        }

        $label = $this->mode === self::MODE_ADVANTAGE ? 'Avantage' : 'Désavantage';

        return $label . ' : jets ' . $this->keptSum . ' et ' . $this->discardedSum . ', ' . $this->keptSum . ' retenu';
    }
}
