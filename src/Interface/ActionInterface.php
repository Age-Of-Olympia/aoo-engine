<?php

namespace App\Interface;

use App\Entity\ActionCondition;
use App\Entity\ActionEffect;
use App\Entity\Race;
use Doctrine\Common\Collections\Collection;
use Player;

interface ActionInterface
{
    public function getId(): ?int;
    public function setId(int $id): self;
    public function getActionConditions(): Collection;
    public function addCondition(ActionCondition $condition): self;
    public function removeCondition(ActionCondition $condition): self;
    public function getEffects(): Collection;
    public function getOnSuccessEffects(bool $success = true): Collection;
    public function addEffect(ActionEffect $effect): self;
    public function removeEffect(ActionEffect $effect): self;
    public function getRaces(): Collection;
    public function addRace(Race $race): self;
    public function removeRace(Race $race): self;
    public function calculateXp(bool $success, Player $actor, Player $target): array;
    public function hideWhenSuccess(): bool;
}