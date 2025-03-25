<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "action_outcomes")]
class ActionOutcome
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Action::class, inversedBy: "outcomes")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Action $action = null;

    #[ORM\Column(type: "boolean", name: "apply_to_self", options: ["default" => false])]
    private bool $applyToSelf = false;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: "boolean", name: "on_success", options: ["default" => true])]
    private bool $onSuccess = false;

    #[ORM\OneToMany(
        mappedBy: "outcome",
        targetEntity: OutcomeInstruction::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(["orderIndex" => "ASC"])]
    private Collection $instructions;

    public function __construct()
    {
        $this->instructions = new ArrayCollection();
    }

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

    public function getAction(): ?Action
    {
        return $this->action;
    }

    public function setAction(?Action $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getApplyToSelf(): bool
    {
        return $this->applyToSelf;
    }

    public function setApplyToSelf(bool $applyToSelf): self
    {
        $this->applyToSelf = $applyToSelf;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
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

    /**
     * @return Collection<int, OutcomeInstruction>
     */
    public function getInstructions(): Collection
    {
        return $this->instructions;
    }

    public function addInstruction(OutcomeInstruction $instruction): self
    {
        if (!$this->instructions->contains($instruction)) {
            $this->instructions->add($instruction);
            $instruction->setEffect($this);
        }
        return $this;
    }

    public function removeInstruction(OutcomeInstruction $instruction): self
    {
        if ($this->instructions->removeElement($instruction)) {
            // set the owning side to null (unless already changed)
            if ($instruction->getEffect() === $this) {
                $instruction->setEffect(null);
            }
        }
        return $this;
    }
}
