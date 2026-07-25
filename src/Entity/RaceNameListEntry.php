<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One named entry of a race's ordered action-name list.
 *
 * Race starter actions and race spells share this shape. The names reference
 * actions by their players_actions string name (e.g. "dmg1/pic_de_pierre"),
 * NOT by FK: part of these names have no row in the `actions` table — des
 * actions héritées, accordées par leur seul nom.
 */
#[ORM\MappedSuperclass]
abstract class RaceNameListEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Race::class)]
    #[ORM\JoinColumn(name: "race_id", nullable: false, onDelete: "CASCADE")]
    private Race $race;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $position = 0;

    public function __construct(Race $race, string $name, int $position)
    {
        $this->race = $race;
        $this->name = $name;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRace(): Race
    {
        return $this->race;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
