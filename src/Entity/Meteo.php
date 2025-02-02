<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "meteos")]
class Meteo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "string", length: 50)]
    private string $coord_computed;

    #[ORM\Column(type: "string", length: 50)]
    private string $mask;

    #[ORM\Column(type: "float")]
    private float $scrollingMask;

    #[ORM\Column(type: "integer", options: array("default"=>0))]
    private int $verticalScrolling = 0;

    public function getCoord_computed(): string
    {
        return $this->coord_computed;
    }

    public function getMask(): string
    {
        return $this->mask;
    }

    public function getScrollingMask(): float
    {
        return $this->scrollingMask;
    }

    public function getVerticalScrolling(): int
    {
        return $this->verticalScrolling;
    }


}
