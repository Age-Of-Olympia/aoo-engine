<?php
namespace App\Entity;

use App\Interface\ActionInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Player;

#[ORM\Entity]
#[ORM\Table(name: "actions")]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
abstract class Action implements ActionInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    protected ?int $id = null;

    #[ORM\Column(type: "string", length: 50)]
    protected string $name;

    #[ORM\Column(type: "string", length: 50, name: "display_name")]
    protected string $displayName;

    #[ORM\OneToMany(
        mappedBy: "action",
        targetEntity: ActionCondition::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(["executionOrder" => "ASC"])]
    protected Collection $actionConditions;

    #[ORM\OneToMany(
        mappedBy: "action",
        targetEntity: ActionOutcome::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true
    )]
    protected Collection $outcomes;

    protected Collection $automaticOutcomeInstructions;

    /**
     * Many Actions can belong to Many Races by default.
     */
    #[ORM\ManyToMany(targetEntity: Race::class, mappedBy: "actions")]
    protected Collection $races;

    protected bool $refreshScreen = false;
    protected bool $hideOnSuccess = false;

    public function __construct()
    {
        $this->actionConditions = new ArrayCollection();
        $this->outcomes    = new ArrayCollection();
        $this->automaticOutcomeInstructions = new ArrayCollection();
        $this->races    = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    /**
     * @return Collection<int, ActionCondition>
     */
    public function getActionConditions(): Collection
    {
        return $this->actionConditions;
    }

    public function addCondition(ActionCondition $condition): self
    {
        if (!$this->actionConditions->contains($condition)) {
            $this->actionConditions->add($condition);
            $condition->setAction($this);
        }
        return $this;
    }

    public function removeCondition(ActionCondition $condition): self
    {
        if ($this->actionConditions->removeElement($condition)) {
            // set the owning side to null (unless already changed)
            if ($condition->getAction() === $this) {
                $condition->setAction(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ActionOutcome>
     */
    public function getOutcomes(): Collection
    {
        return $this->outcomes;
    }

    /**
     * @return Collection<int, OutcomeInstruction>
     */
    public function getAutomaticOutcomeInstructions(): Collection
    {
        return $this->automaticOutcomeInstructions;
    }

    public function addAutomaticOutcomeInstruction(OutcomeInstruction $outcomeInstruction): self
    {
        if (!$this->automaticOutcomeInstructions->contains($outcomeInstruction)) {
            $this->automaticOutcomeInstructions->add($outcomeInstruction);
        }
        return $this;
    }

    public function removeAutomaticOutcomeInstruction(OutcomeInstruction $outcome): self
    {
        $this->automaticOutcomeInstructions->removeElement($outcome);
        return $this;
    }

    public function initAutomaticOutcomeInstructions(): self
    {
        $this->automaticOutcomeInstructions = new ArrayCollection();
        return $this;
    }

    /**
     * @return Collection<int, ActionOutcome>
     */
    public function getOnSuccessOutcomes(bool $success = true): Collection
    {
        $filteredCollection = $this->outcomes->filter(function($element) use ($success) {
            return $element->isOnSuccess() == $success;
        });
        return $filteredCollection;
    }

    public function addOutcome(ActionOutcome $outcome): self
    {
        if (!$this->outcomes->contains($outcome)) {
            $this->outcomes->add($outcome);
            $outcome->setAction($this);
        }
        return $this;
    }

    public function removeOutcome(ActionOutcome $outcome): self
    {
        if ($this->outcomes->removeElement($outcome)) {
            // set the owning side to null (unless already changed)
            if ($outcome->getAction() === $this) {
                $outcome->setAction(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Race>
     */
    public function getRaces(): Collection
    {
        return $this->races;
    }

    public function addRace(Race $race): self
    {
        if (!$this->races->contains($race)) {
            $this->races->add($race);
            $race->addAction($this); // keep it bidirectional
        }
        return $this;
    }

    public function removeRace(Race $race): self
    {
        if ($this->races->removeElement($race)) {
            $race->removeAction($this);
        }
        return $this;
    }

    abstract public function calculateXp(bool $success, Player $actor, Player $target): array;

    public function hideOnSuccess(): bool {
        return $this->hideOnSuccess;
    }

    public function setHideOnSuccess(bool $hide): void {
        $this->hideOnSuccess = $hide;
    }

    public function refreshScreen(): bool {
        return $this->refreshScreen;
    }

    public function setRefreshScreen(bool $refresh): void {
        $this->refreshScreen = $refresh;
    }

    public function activateAntiBerserk(): bool {
        return false;
    }
}
