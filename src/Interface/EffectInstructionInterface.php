<?php

namespace App\Interface;

use App\Action\EffectInstruction\EffectResult;
use App\Entity\ActionEffect;
use Player;

interface EffectInstructionInterface
{
    public function getId(): ?int;
    public function setId(int $id): self;
    public function getEffect(): ?ActionEffect;
    public function setEffect(?ActionEffect $effect): self;
    public function getParameters(): ?array;
    public function setParameters(?array $parameters): self;
    public function getOrderIndex(): int;
    public function setOrderIndex(int $orderIndex): self;
    public function execute(Player $actor, Player $target): EffectResult;
}