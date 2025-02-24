<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "actions")]
class Action
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\OneToMany(
        mappedBy: "action",
        targetEntity: ActionCondition::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(["execution_order" => "ASC"])]
    private Collection $conditions;

    #[ORM\OneToMany(
        mappedBy: "action",
        targetEntity: ActionEffect::class,
        cascade: ["persist", "remove"],
        orphanRemoval: true
    )]
    private Collection $effects;

    /**
     * Many Actions can belong to Many Races by default.
     */
    #[ORM\ManyToMany(targetEntity: Race::class, mappedBy: "actions")]
    private Collection $races;

    public function __construct()
    {
        $this->conditions = new ArrayCollection();
        $this->effects    = new ArrayCollection();
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

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return Collection<int, ActionCondition>
     */
    public function getConditions(): Collection
    {
        return $this->conditions;
    }

    public function addCondition(ActionCondition $condition): self
    {
        if (!$this->conditions->contains($condition)) {
            $this->conditions->add($condition);
            $condition->setAction($this);
        }
        return $this;
    }

    public function removeCondition(ActionCondition $condition): self
    {
        if ($this->conditions->removeElement($condition)) {
            // set the owning side to null (unless already changed)
            if ($condition->getAction() === $this) {
                $condition->setAction(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ActionEffect>
     */
    public function getEffects(): Collection
    {
        return $this->effects;
    }

    /**
     * @return Collection<int, ActionEffect>
     */
    public function getOnSuccessEffects(bool $success = true): Collection
    {
        $filteredCollection = $this->effects->filter(function($element) use ($success) {
            return $element->onSuccess == $success;
        });
        return $filteredCollection;
    }

    public function addEffect(ActionEffect $effect): self
    {
        if (!$this->effects->contains($effect)) {
            $this->effects->add($effect);
            $effect->setAction($this);
        }
        return $this;
    }

    public function removeEffect(ActionEffect $effect): self
    {
        if ($this->effects->removeElement($effect)) {
            // set the owning side to null (unless already changed)
            if ($effect->getAction() === $this) {
                $effect->setAction(null);
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
}
