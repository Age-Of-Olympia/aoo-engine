<?php
namespace App\Action\Condition;

// Object that will contain anything that can be useful to compute conditions
class ConditionObject 
{
    protected ?int $actorRoll = null;
    protected ?int $targetRoll = null;
    protected ?int $actorRollBonus = null;
    protected ?int $targetRollBonus = null;
    protected ?bool $actorAdvantage = null;
    protected ?bool $targetAdvantage = null;
    protected ?bool $actorDisadvantage = null;
    protected ?bool $targetDisadvantage = null;


    public function __construct() {
    }

    public function getActorRollBonus(): ?int
    {
        return $this->actorRollBonus;
    }

    public function setActorRollBonus(int $actorRollBonus): self
    {
        $this->actorRollBonus = $actorRollBonus;
        return $this;
    }

    public function getActorRoll(): ?int
    {
        return $this->actorRoll;
    }

    public function setActorRoll(int $actorRoll): self
    {
        $this->actorRoll = $actorRoll;
        return $this;
    }

    public function addActorRollBonus(int $actorBonus): self
    {
        $this->actorRollBonus = ($this->actorRollBonus ?? 0) + $actorBonus;
        return $this;
    }

    public function getTargetRollBonus(): ?int
    {
        return $this->targetRollBonus;
    }

    public function setTargetRollBonus(int $targetRollBonus): self
    {
        $this->targetRollBonus = $targetRollBonus;
        return $this;
    }

    public function getTargetRoll(): ?int
    {
        return $this->targetRoll;
    }

    public function setTargetRoll(int $targetRoll): self
    {
        $this->targetRoll = $targetRoll;
        return $this;
    }

    public function addTargetRollBonus(int $targetBonus): self
    {
        $this->targetRollBonus = ($this->targetRollBonus ?? 0) + $targetBonus;
        return $this;
    }

    public function getActorAdvantage(): ?bool
    {
        return $this->actorAdvantage;
    }

    public function setActorAdvantage(bool $actorAdvantage): self
    {
        $this->actorAdvantage = $actorAdvantage;
        return $this;
    }

    public function getTargetAdvantage(): ?bool
    {
        return $this->targetAdvantage;
    }

    public function setTargetAdvantage(bool $targetAdvantage): self
    {
        $this->targetAdvantage = $targetAdvantage;
        return $this;
    }

    public function getActorDisadvantage(): ?bool
    {
        return $this->actorDisadvantage;
    }

    public function setActorDisadvantage(bool $actorDisadvantage): self
    {
        $this->actorDisadvantage = $actorDisadvantage;
        return $this;
    }

    public function getTargetDisadvantage(): ?bool
    {
        return $this->targetDisadvantage;
    }

    public function setTargetDisadvantage(bool $targetDisadvantage): self
    {
        $this->targetDisadvantage = $targetDisadvantage;
        return $this;
    }

}