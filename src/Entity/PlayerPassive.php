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

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ActionPassive::class)]
    #[ORM\JoinColumn(name: "passive_id", referencedColumnName: "id")]
    private ActionPassive $passive;

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id)
    {
        $this->player_id = $player_id;
    }

    public function getPassive(): ActionPassive
    {
        return $this->passive;
    }

    public function setPassive(ActionPassive $passive)
    {
        $this->passive = $passive;
    }
}
