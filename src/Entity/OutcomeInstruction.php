<?php
namespace App\Entity;

use App\Action\OutcomeInstruction\OutcomeResult;
use App\Interface\OutcomeInstructionInterface;
use Doctrine\ORM\Mapping as ORM;
use Classes\Player;

#[ORM\Entity]
#[ORM\Table(name: "outcome_instructions")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
abstract class OutcomeInstruction implements OutcomeInstructionInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ActionOutcome::class, inversedBy: "instructions")]
    #[ORM\JoinColumn(nullable: false)]
    private ?ActionOutcome $outcome = null;

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

    public function getOutcome(): ?ActionOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(?ActionOutcome $outcome): self
    {
        $this->outcome = $outcome;
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

    abstract public function execute(Player $actor, Player $target, array $rollsArray): OutcomeResult;
}
