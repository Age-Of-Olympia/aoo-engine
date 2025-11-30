<?php
namespace App\Entity;

use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Classes\Player;

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
    protected string $icon;

    #[ORM\Column(type: "string", length: 50)]
    protected string $name;

    #[ORM\Column(type: "string", length: 50, name: "display_name")]
    protected string $displayName;

    #[ORM\Column(type: "string", length: 150)]
    protected string $text;

    #[ORM\Column(type: "integer", length: 50)]
    protected int $level;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    protected ?string $race = null;

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
    protected string $ormType;

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

    public function getOrmType(): string
    {
        return $this->ormType;
    }

    public function setOrmType(string $ormType): void
    {
        $this->ormType = $ormType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    /**
     * @return Collection<int, ActionCondition>
     */
    public function getConditions(): Collection
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
        if (!isset($this->automaticOutcomeInstructions)) {
            $this->automaticOutcomeInstructions = new ArrayCollection();
        }
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
        if (!isset($this->automaticOutcomeInstructions)) {
            $this->automaticOutcomeInstructions = new ArrayCollection();
        }
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

    public function calculateXp(bool $success, ActorInterface $actor, ActorInterface $target): array
    {
        $actorXp = $this->calculateActorXp($success, $actor, $target);
        $targetXp = $this->calculateTargetXp($success, $actor, $target);
        $xpResultsArray["actor"] = $actorXp;
        $xpResultsArray["target"] = $targetXp;
        return $xpResultsArray;
    }

    protected function calculateActorXp(bool $success, ActorInterface $actor, ActorInterface $target): int {
        return 1;
    }

    protected function calculateTargetXp(bool $success, ActorInterface $actor, ActorInterface $target): int {
        return 1;
    }

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
