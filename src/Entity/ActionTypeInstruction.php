<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A configurable outcome instruction attached to an action *type* (e.g. "attack")
 * rather than a single action. Every action whose class ancestry includes that
 * type inherits it at execution — the data-driven replacement for the automatic
 * instructions that used to be hardcoded in AttackAction. It stores only the
 * config (which instruction + its params); the executable OutcomeInstruction is
 * built from it via OutcomeInstructionFactory.
 */
#[ORM\Entity]
#[ORM\Table(name: "action_type_instructions")]
#[ORM\Index(name: "idx_action_type_instructions_type_key", columns: ["type_key"])]
class ActionTypeInstruction implements TypeChildConfigInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100, name: "type_key")]
    private string $typeKey;

    #[ORM\Column(type: "string", length: 50, name: "instruction_type")]
    private string $instructionType;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parameters = null;

    #[ORM\Column(type: "integer", name: "order_index", options: ["default" => 0])]
    private int $orderIndex = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTypeKey(): string
    {
        return $this->typeKey;
    }

    public function setTypeKey(string $typeKey): self
    {
        $this->typeKey = $typeKey;
        return $this;
    }

    public function getInstructionType(): string
    {
        return $this->instructionType;
    }

    public function setInstructionType(string $instructionType): self
    {
        $this->instructionType = $instructionType;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    public function setParameters(?array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }
}
