<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "action_passives")]
class ActionPassive
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "json")]
    private array $traits;

    #[ORM\Column(type: "string", length: 255)]
    private string $type;

    #[ORM\Column(type: "string", length: 255)]
    private string $carac;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2)]
    private ?string $value = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $conditions = null;

    #[ORM\Column(type: "integer")]
    private int $level;

    #[ORM\Column(type: "string", length: 255)]
    private string $race;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTraits(): array
    {
        return $this->traits;
    }

    public function setTraits(array $traits): void
    {
        $this->traits = $traits;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getCarac(): string
    {
        return $this->carac;
    }

    public function setCarac(string $carac): void
    {
        $this->carac = $carac;
    }

    public function getValue(): float
    {
        return $this->value !== null ? (float) $this->value : 0.0;
    }

    public function setValue(float $value): void
    {
        // La valeur est stockée en string avec Doctrine
        $this->value = number_format($value, 2, '.', '');
    }

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(?array $conditions): self
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level)
    {
        $this->level = $level;
    }

    public function getRace(): string
    {
        return $this->race;
    }

    public function setRace(string $race): void
    {
        $this->race = $race;
    }
}
