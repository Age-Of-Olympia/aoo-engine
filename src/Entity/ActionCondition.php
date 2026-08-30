<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "action_conditions")]
class ActionCondition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $conditionType;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parameters = null;

    // The inverse side is Action::$actionConditions; getConditions() is only its
    // accessor. Naming the accessor here left Doctrine looking up an association
    // that does not exist, and removing a condition warned instead of working.
    #[ORM\ManyToOne(targetEntity: Action::class, inversedBy: "actionConditions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Action $action = null;

    #[ORM\Column(type: "integer", name: "execution_order")]
    private ?int $executionOrder = null;

    #[ORM\Column(type: "boolean")]
    private bool $blocking = false;

    /**
     * Contexte d'affichage : la condition gate AUSSI l'affichage du
     * bouton dans le panneau d'observation (évaluée au rendu par
     * ActionTargeting::matchesDisplayContext) — ex. RequiresDistance
     * contextuelle = bouton visible seulement à portée.
     */
    #[ORM\Column(type: "boolean", name: "display_context", options: ["default" => false])]
    private bool $displayContext = false;

    // -------------------------
    // Getters & Setters
    // -------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
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

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function setParameters(?array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getExecutionOrder(): ?int
    {
        return $this->executionOrder;
    }

    public function setExecutionOrder(int $executionOrder): self
    {
        $this->executionOrder = $executionOrder;
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

    public function isDisplayContext(): bool
    {
        return $this->displayContext;
    }

    public function setDisplayContext(bool $displayContext): self
    {
        $this->displayContext = $displayContext;
        return $this;
    }
}
