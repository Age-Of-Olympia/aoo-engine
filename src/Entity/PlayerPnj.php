<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity]
#[ORM\Table(name: "players_pnjs")]
class PlayerPnj
{
    
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $player_id;

    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private int $pnj_id;

    #[ORM\Column(type: "boolean")]
    private bool $displayed;

    public function getPlayerId(): int
    {
        return $this->player_id;
    }

    public function setPlayerId(int $player_id): void
    {
        $this->player_id = $player_id;
    }

    public function getPnjId(): int
    {
        return $this->pnj_id;
    }

    public function setPnjId(int $pnj_id): void
    {
        $this->pnj_id = $pnj_id;
    }

    public function isDisplayed(): bool
    {
        return $this->displayed;
    }

    public function setDisplayed(bool $displayed): void
    {
        $this->displayed = $displayed;
    }
}
