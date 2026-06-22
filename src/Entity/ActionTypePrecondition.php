<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A condition attached to an action *type* (e.g. "attack") — or globally to all
 * actions (an empty type_key) — that runs as a precondition before the action's
 * own conditions. The data-driven replacement for the preconditions that used to
 * be hardcoded (BaseCondition's PlanCondition/enfers, the *Compute conditions'
 * Obstacle/Dodge/NoBerserk/AntiSpell). It stores only the config (which condition
 * + its params + whether failure blocks); the runnable ActionCondition is built
 * from it by ActionTypePreconditionResolver.
 *
 * Same shape as action_type_instructions but for conditions: a type_key, the
 * condition type, JSON parameters, an order and a blocking flag.
 */
#[ORM\Entity]
#[ORM\Table(name: "action_type_preconditions")]
#[ORM\Index(name: "idx_action_type_preconditions_type_key", columns: ["type_key"])]
class ActionTypePrecondition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    /** Empty string = global (every action); otherwise an action type key. */
    #[ORM\Column(type: "string", length: 100, name: "type_key")]
    private string $typeKey = '';

    #[ORM\Column(type: "string", length: 100, name: "condition_type")]
    private string $conditionType;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parameters = null;

    #[ORM\Column(type: "integer", name: "order_index", options: ["default" => 0])]
    private int $orderIndex = 0;

    #[ORM\Column(type: "boolean", options: ["default" => true])]
    private bool $blocking = true;

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

    public function getConditionType(): string
    {
        return $this->conditionType;
    }

    public function setConditionType(string $conditionType): self
    {
        $this->conditionType = $conditionType;
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

    public function isBlocking(): bool
    {
        return $this->blocking;
    }

    public function setBlocking(bool $blocking): self
    {
        $this->blocking = $blocking;
        return $this;
    }
}
