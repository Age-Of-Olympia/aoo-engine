<?php

namespace App\Interface;

use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Entity\Race;
use Doctrine\Common\Collections\Collection;
use Player;

interface ActionInterface
{
    public function getId(): ?int;
    public function setId(int $id): self;
    public function getName(): string;
    public function getActionConditions(): Collection;
    public function addCondition(ActionCondition $condition): self;
    public function removeCondition(ActionCondition $condition): self;
    public function getOutcomes(): Collection;
    public function getOnSuccessOutcomes(bool $success = true): Collection;
    public function addOutcome(ActionOutcome $outcome): self;
    public function removeOutcome(ActionOutcome $outcome): self;
    public function getRaces(): Collection;
    public function addRace(Race $race): self;
    public function removeRace(Race $race): self;
    public function calculateXp(bool $success, Player $actor, Player $target): array;
    public function getLogMessages(Player $actor, Player $target): array;
    public function hideOnSuccess(): bool;
    public function setHideOnSuccess(bool $hide): void;
}