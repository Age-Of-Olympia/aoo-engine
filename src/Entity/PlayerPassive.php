<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "players_passives")]
class PlayerPassive
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

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

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id)
    {
        $this->player_id = $player_id;
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
}
