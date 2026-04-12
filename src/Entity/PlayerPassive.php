<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "players_passives")]
class PlayerPassive
{
    #[ORM\Id]
    #[ORM\Column(type: "integer", name: "player_id")]
    private int $playerId;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: ActionPassive::class)]
    #[ORM\JoinColumn(name: "passive_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ActionPassive $passive;

    public function getPlayerId(): int
    {
        return $this->playerId;
    }

    public function setPlayerId(int $playerId): self
    {
        $this->playerId = $playerId;
        return $this;
    }

    public function getPassive(): ActionPassive
    {
        return $this->passive;
    }

    public function setPassive(ActionPassive $passive): self
    {
        $this->passive = $passive;
        return $this;
    }
}
