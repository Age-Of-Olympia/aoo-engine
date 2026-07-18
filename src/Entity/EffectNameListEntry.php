<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One named entry of an effect's ordered name list — the corruption
 * materials and the controlled-effects list share this shape (même
 * patron que RaceNameListEntry).
 */
#[ORM\MappedSuperclass]
abstract class EffectNameListEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Effect::class)]
    #[ORM\JoinColumn(name: "effect_id", nullable: false, onDelete: "CASCADE")]
    private Effect $effect;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $position = 0;

    public function __construct(Effect $effect, string $name, int $position)
    {
        $this->effect = $effect;
        $this->name = $name;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEffect(): Effect
    {
        return $this->effect;
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
