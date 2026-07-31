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
     * Ce type se répare-t-il ? `null` = ce que dit sa FAMILLE.
     *
     * Réparer visait « toute structure », ce qui était vrai tant que les seules
     * structures étaient bâties. Depuis que ressources, décors et plantes sont
     * des entités, la même porte s'est ouverte sur eux : on pouvait réparer une
     * fleur — et payer une action pour le faire.
     *
     * Nullable À DESSEIN : une colonne semée à la main aurait figé la règle du
     * jour de la migration. Ici, un type qui n'a rien décidé suit sa famille,
     * et changer la règle d'une famille suit tous ceux qui s'en remettent à
     * elle. Cocher la case sur un type précis reste possible et l'emporte.
     */
    #[ORM\Column(type: "boolean", name: "repairable", nullable: true)]
    private ?bool $repairable = null;

    public function isRepairable(): bool
    {
        return $this->repairable ?? $this->repairableByDefault();
    }

    /** Ce que la famille dit, faute de décision sur le type. */
    protected function repairableByDefault(): bool
    {
        return false;
    }

    /**
     * Ce qui est ÉCRIT sur ce type, sans la réponse de la famille : `null`
     * quand rien n'a été décidé. L'écran de réglage en a besoin — proposer
     * « Oui / Non » seulement effacerait la nuance dès la première sauvegarde.
     */
    public function getRepairableOverride(): ?bool
    {
        return $this->repairable;
    }

    /** Ce que la famille répondrait, pour l'afficher en face du « défaut ». */
    public function repairableFamilyDefault(): bool
    {
        return $this->repairableByDefault();
    }

    /** `null` rend la décision à la famille. */
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
