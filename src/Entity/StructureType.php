<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que toute chose POSÉE partage, quelle que soit sa famille.
 *
 * Bâtiments, décors et récoltables ont en commun d'occuper le plateau : on
 * peut lire ce qui est écrit dessus, et un exemplaire neuf naît avec une
 * inscription — ou muet. Un peuple, lui, écrit son message du jour lui-même
 * et n'a que faire de ces deux colonnes.
 *
 * Miroir de `Structure` du côté OBJET, où `Resource extends Structure` : les
 * deux hiérarchies se répondent, l'une décrivant les types, l'autre ce qui est
 * posé.
 *
 * Abstraite et hors du discriminant : aucune ligne n'est « une structure » tout
 * court, elle est toujours l'une des trois.
 */
#[ORM\Entity]
abstract class StructureType extends Race
{
    /**
     * Jusqu'où se lit ce qui est inscrit : de loin (pancarte, enseigne) ou
     * seulement d'une case voisine (plaque gravée, épitaphe).
     */
    #[ORM\Column(type: "boolean", name: "readable_from_afar", options: ["default" => 0])]
    private bool $readableFromAfar = false;

    /**
     * Ce qu'un exemplaire NEUF porte déjà d'inscrit — copié à la pose, puis
     * libre : changer ce défaut ne réécrit pas ce qui est déjà posé.
     */
    #[ORM\Column(type: "text", name: "default_text", nullable: true)]
    private ?string $defaultText = null;

    /**
     * Does this type mend? `null` = whatever its family says.
     *
     * Nullable on purpose: an undecided type follows its family, so changing a
     * family default carries every type that never overrode it. Ticking the box
     * on one type still wins.
     */
    #[ORM\Column(type: "boolean", name: "repairable", nullable: true)]
    private ?bool $repairable = null;

    public function isRepairable(): bool
    {
        return $this->repairable ?? $this->repairableByDefault();
    }

    /** The family's answer, when the type has not decided. */
    protected function repairableByDefault(): bool
    {
        return false;
    }

    /**
     * What is written on the type, `null` when undecided — the settings screen
     * needs the third state, or saving once would cut the type off its family.
     */
    public function getRepairableOverride(): ?bool
    {
        return $this->repairable;
    }

    /** The family's answer, to show beside the "default" option. */
    public function repairableFamilyDefault(): bool
    {
        return $this->repairableByDefault();
    }

    /** `null` hands the decision back to the family. */
    public function setRepairable(?bool $repairable): self
    {
        $this->repairable = $repairable;

        return $this;
    }

    public function isReadableFromAfar(): bool
    {
        return $this->readableFromAfar;
    }

    public function setReadableFromAfar(bool $readable): self
    {
        $this->readableFromAfar = $readable;

        return $this;
    }

    public function getDefaultText(): string
    {
        return (string) $this->defaultText;
    }

    public function setDefaultText(string $text): self
    {
        $this->defaultText = $text;

        return $this;
    }
}
