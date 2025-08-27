<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "items")]
class Item
{
    public function __construct()
    {
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $name = null;

   #[ORM\Column(type: "boolean")]
    private bool $private = false;

    #[ORM\Column(type: "boolean")]
    private bool $enchanted = false;

    #[ORM\Column(type: "boolean")]
    private bool $vorpal = false;

    #[ORM\Column(type: "boolean")]
    private bool $cursed = false;

    #[ORM\Column(type: "string", length: 255)]
    private string $element = '';

    #[ORM\Column(type: "string", length: 255)]
    private ?string $spell= null;

    //getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }
    public function setId(int $id): void
    {
        $this->id = $id;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }
    public function setPrivate(bool $private): void
    {
        $this->private = $private;  
    }

    public function isEnchanted(): bool
    {
        return $this->enchanted;
    }
    public function setEnchanted(bool $enchanted): void
    {
        $this->enchanted = $enchanted;
    }

    public function isVorpal(): bool
    {
        return $this->vorpal;
    }
    public function setVorpal(bool $vorpal): void
    {
        $this->vorpal = $vorpal;
    }

    public function isCursed(): bool
    {
        return $this->cursed;
    }
    public function setCursed(bool $cursed): void
    {
        $this->cursed = $cursed;
    }

    public function getElement(): string
    {
        return $this->element;
    }

    public function setElement(string $element): void
    {
        $this->element = $element;
    }

    public function getSpell(): ?string
    {
        return $this->spell;
    }

    public function setSpell(?string $spell): void
    {
        $this->spell = $spell;
    }
}


