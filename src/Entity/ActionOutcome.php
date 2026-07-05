<?php
namespace App\Entity;

use App\Enum\OutcomeTarget;
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

    #[ORM\Column(type: "string", length: 10, name: "apply_to", enumType: OutcomeTarget::class, options: ["default" => "target"])]
    private OutcomeTarget $applyTo = OutcomeTarget::Target;

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

    public function getApplyTo(): OutcomeTarget
    {
        return $this->applyTo;
    }

    public function setApplyTo(OutcomeTarget $applyTo): self
    {
        $this->applyTo = $applyTo;
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
            $instruction->setOutcome($this);
        }
        return $this;
    }

    public function removeInstruction(OutcomeInstruction $instruction): self
    {
        if ($this->instructions->removeElement($instruction)) {
            // set the owning side to null (unless already changed)
            if ($instruction->getOutcome() === $this) {
                $instruction->setOutcome(null);
            }
        }
        return $this;
    }
}
