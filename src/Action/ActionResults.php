<?php
namespace App\Action;

/**
 * A simple DTO class used to communicate the result of executing an action.
 */
class ActionResults
{
    public function __construct(
        private bool $success,
        private array $conditionsResultsArray,
        private array $effectsResultsArray,
        private array $costsResultsArray,
        private array $xpResultsArray,
        private array $logsArray
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getConditionsResultsArray(): array
    {
        return $this->conditionsResultsArray;
    }

    public function setConditionsResultsArray(array $conditionsResultsArray): self
    {
        $this->conditionsResultsArray = $conditionsResultsArray;
        return $this;
    }

    public function getEffectsResultsArray(): array
    {
        return $this->effectsResultsArray;
    }

    public function setEffectsResultsArray(array $effectsResultsArray): self
    {
        $this->effectsResultsArray = $effectsResultsArray;
        return $this;
    }

    public function getCostsResultsArray(): array
    {
        return $this->costsResultsArray;
    }

    public function setCostsResultsArray(array $costsResultsArray): self
    {
        $this->costsResultsArray = $costsResultsArray;
        return $this;
    }

    public function getXpResultsArray(): array
    {
        return $this->xpResultsArray;
    }

    public function setXpResultsArray(array $xpResultsArray): self
    {
        $this->xpResultsArray = $xpResultsArray;
        return $this;
    }

    public function getLogsArray(): array
    {
        return $this->logsArray;
    }

    public function setLogsArray(array $logsArray): self
    {
        $this->logsArray = $logsArray;
        return $this;
    }

    
}
