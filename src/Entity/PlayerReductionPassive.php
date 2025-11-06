<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "players_reduction_passives")]
class PlayerReductionPassive
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    public function getPlayerId(): int
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
}
