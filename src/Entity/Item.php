<?php
namespace App\Entity;

use App\Interface\LockableInterface;
use App\Interface\OwnsCaracsInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "items")]
class Item implements OwnsCaracsInterface, LockableInterface
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

    /** Ce type d'objet se ferme-t-il ? Un coffre oui, une épée non. */
    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $lockable = false;

    public function isLockable(): bool
    {
        return $this->lockable;
    }

    public function setLockable(bool $lockable): void
    {
        $this->lockable = $lockable;
    }

    /** Base life of an item of this type — the counterpart of `races.pv`. */
    #[ORM\Column(type: "integer", name: "durability_max", options: ["default" => 100])]
    private int $durabilityMax = 100;

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

    public function getDurabilityMax(): int
    {
        return $this->durabilityMax;
    }

    public function setDurabilityMax(int $durabilityMax): void
    {
        $this->durabilityMax = $durabilityMax;
    }

    /**
     * An item owns only its life. The other fifteen columns are what it lends
     * its bearer, so they stay at zero here — see {@see OwnsCaracsInterface}.
     */
    public function ownCaracs(): array
    {
        $own = array_fill_keys(\App\Enum\Caracs::KEYS, 0);
        $own['pv'] = $this->durabilityMax;

        return $own;
    }
}


