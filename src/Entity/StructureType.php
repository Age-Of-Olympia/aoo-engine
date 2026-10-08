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
    /** Les murs, bâtiments et ressources partagent le stock historique. */
    public function imageDir(): string
    {
        return 'walls';
    }

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

    #[ORM\Column(type: "string", length: 100, name: "default_dialog", options: ["default" => ""])]
    private string $defaultDialog = '';

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

    /** Dialogue (dialogs.name) copied onto every new exemplar, '' = none. */
    public function getDefaultDialog(): string
    {
        return $this->defaultDialog;
    }

    public function setDefaultDialog(string $dialog): self
    {
        $this->defaultDialog = $dialog;

        return $this;
    }
}
