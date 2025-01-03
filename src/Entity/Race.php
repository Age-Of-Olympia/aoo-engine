<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "races")]
class Race
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 50, unique: true)]
    private string $code;

    #[ORM\Column(type: "string", length: 100)]
    private string $name;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "boolean")]
    private string $playable;

    #[ORM\Column(type: "boolean")]
    private string $hidden;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $portraitNextNumber = 1;

    #[ORM\Column(type: "integer", options: array("default"=>1))]
    private int $avatarNextNumber = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescrition(string $description): void
    {
        $this->description = $description;
    }

    public function getPlayable(): bool
    {   
        return $this->playable;
    }

    public function getHidden(): bool
    {   
        return $this->hidden;
    }

    public function getPortraitNextNumber(): int
    {
        return $this->portraitNextNumber;
    }

    public function incrementPortraitNextNumber(): self
    {
        $this->portraitNextNumber++;
        return $this;
    }

    public function getAvatarNextNumber(): int
    {
        return $this->avatarNextNumber;
    }

    public function incrementAvatarNextNumber(): self
    {
        $this->avatarNextNumber++;
        return $this;
    }


}
