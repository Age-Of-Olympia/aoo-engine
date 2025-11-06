<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "players_bonus")]
class PlayerBonus
{   
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $n = 0;

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

    public function getN(): int
    {
        return $this->n;
    }

    public function setN(int $n):void
    {
        $this->n = $n;
    }
}