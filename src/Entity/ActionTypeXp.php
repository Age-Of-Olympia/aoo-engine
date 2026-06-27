<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The XP rule attached to an action *type* — the data-driven replacement for the
 * calculate*Xp() methods. `mode` selects the algorithm family (fixed reward, or
 * the combat/steal/train algorithms) and `params` holds its tuning knobs; both
 * are interpreted by {@see \App\Service\Action\Xp\XpCalculatorRegistry}.
 *
 * An action inherits the closest type in its ancestry that has a row (a spell
 * has no "spell" row, so it falls back to "attack").
 */
#[ORM\Entity]
#[ORM\Table(name: "action_type_xp")]
class ActionTypeXp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100, name: "type_key", unique: true)]
    private string $typeKey;

    #[ORM\Column(type: "string", length: 30)]
    private string $mode;

    /** @var array<string, int>|null */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $params = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTypeKey(): string
    {
        return $this->typeKey;
    }

    public function setTypeKey(string $typeKey): self
    {
        $this->typeKey = $typeKey;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    /** @return array<string, int> */
    public function getParams(): array
    {
        return $this->params ?? [];
    }

    /** @param array<string, int>|null $params */
    public function setParams(?array $params): self
    {
        $this->params = $params;
        return $this;
    }
}
