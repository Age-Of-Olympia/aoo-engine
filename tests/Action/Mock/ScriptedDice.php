<?php

namespace Tests\Action\Mock;

use Classes\Dice;

class ScriptedDice extends Dice
{
    /** @var array<int, array<int, int>> */
    private array $rolls;

    /**
     * @param array<int, array<int, int>> $rolls Queue of roll results, one array per roll() call.
     */
    public function __construct(array $rolls)
    {
        parent::__construct(3);
        $this->rolls = $rolls;
    }

    public function roll($d)
    {
        if (empty($this->rolls)) {
            throw new \RuntimeException('ScriptedDice exhausted: more roll() calls than scripted results.');
        }

        return array_shift($this->rolls);
    }
}
