<?php

namespace App\Interface;

use App\Action\OutcomeInstruction\OutcomeResult;
use App\Entity\ActionOutcome;
use Classes\Player;

interface OutcomeInstructionInterface
{
    public function getId(): ?int;
    public function setId(int $id): self;
    public function getOutcome(): ?ActionOutcome;
    public function setOutcome(?ActionOutcome $effect): self;
    public function getParameters(): ?array;
    public function setParameters(?array $parameters): self;
    public function getOrderIndex(): int;
    public function setOrderIndex(int $orderIndex): self;
    public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult;
}