<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "effect_instructions")]
class EffectInstruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ActionEffect::class, inversedBy: "instructions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?ActionEffect $effect = null;

    #[ORM\Column(type: "string", length: 50)]
    private string $operation;

    #[ORM\Column(type: "boolean")]
    private bool $onSuccess;

    #[ORM\Column(type: "boolean")]
    private bool $onFailure;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $parameters = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $orderIndex = 0;

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

    public function getEffect(): ?ActionEffect
    {
        return $this->effect;
    }

    public function setEffect(?ActionEffect $effect): self
    {
        $this->effect = $effect;
        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    public function isOnSuccess(): bool
    {
        return $this->onSuccess;
    }

    public function setOnSuccess(bool $onSuccess): self
    {
        $this->onSuccess = $onSuccess;
        return $this;
    }

    public function isOnFailure(): bool
    {
        return $this->onFailure;
    }

    public function setOnFailure(bool $onFailure): self
    {
        $this->onFailure = $onFailure;
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
