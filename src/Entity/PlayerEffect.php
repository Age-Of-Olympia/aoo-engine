<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "players_effects")]
class PlayerEffect
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Id]
    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $endTime;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $value;

    public function getPlayer_id(): int
    {
        return $this->player_id;
    }

    public function setPlayer_id(int $player_id)
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

    public function getEndTime(): ?int
    {
        return $this->endTime;
    }

    public function setEndTime(?int $endTime):void
    {
        $this->endTime = $endTime;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(?int $value): void
    {
        $this->value = $value;
    }
}
