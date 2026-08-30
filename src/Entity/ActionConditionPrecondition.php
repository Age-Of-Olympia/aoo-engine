<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A precondition attached to a CONDITION type (e.g. "MeleeCompute") rather than
 * an action or action type. The data-driven replacement for the preconditions
 * the *Compute conditions array_push into their own preConditions in code
 * (Dodge/NoBerserk/Obstacle/AntiSpell). When a condition of $parentConditionType
 * is checked, these run first as its preconditions — exactly as before, but as
 * config keyed on the condition, which is where the behaviour actually lives (a
 * "spell" action can carry a MeleeCompute, so this can't be keyed on action type).
 *
 * Stores only the config: the parent condition, the precondition's condition
 * type, optional JSON params and an order.
 */
#[ORM\Entity]
#[ORM\Table(name: "action_condition_preconditions")]
#[ORM\Index(name: "idx_action_condition_preconditions_parent", columns: ["parent_condition_type"])]
class ActionConditionPrecondition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100, name: "parent_condition_type")]
    private string $parentConditionType;

    #[ORM\Column(type: "string", length: 100, name: "precondition_type")]
    private string $preconditionType;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parameters = null;

    #[ORM\Column(type: "integer", name: "order_index", options: ["default" => 0])]
    private int $orderIndex = 0;

    /**
     * A failure here REFUSES the action rather than failing it: the executor
     * stops before the outcomes and before the costs.
     *
     * The distinction is the player's. A dodge is a paid failure — the arrow
     * did leave. An obstacle on the line of fire, an anti-Berserk window, a
     * helmet that forbids magic: the character sees the gesture is
     * impossible and does not attempt it.
     *
     * The flag lives HERE and not on the parent condition, whose `blocking`
     * states what that condition IS for its whole existence — a
     * DistanceCompute is not blocking, or a missed shot would be free. The
     * refusal belongs to the precondition that pronounced it.
     */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $blocking = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getParentConditionType(): string
    {
        return $this->parentConditionType;
    }

    public function setParentConditionType(string $parentConditionType): self
    {
        $this->parentConditionType = $parentConditionType;
        return $this;
    }

    public function getPreconditionType(): string
    {
        return $this->preconditionType;
    }

    public function setPreconditionType(string $preconditionType): self
    {
        $this->preconditionType = $preconditionType;
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
