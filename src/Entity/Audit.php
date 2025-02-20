<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "audit")]
class Audit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private $id;

    #[ORM\Column(type: "integer", name: "audit_key", nullable: true)]
    private $auditKey;

    #[ORM\Column(type: "string")]
    private $action;

    #[ORM\Column(type: "datetime")]
    private $timestamp;

    #[ORM\Column(type: "integer", name: "user_id", nullable: true)]
    private $userId;

    #[ORM\Column(type: "string", name: "ip_address", nullable: true)]
    private $ipAddress;

    #[ORM\Column(type: "text", nullable: true)]
    private $details;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuditKey(): ?int
    {
        return $this->auditKey;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAuditKey(int $key): self
    {
        $this->auditKey = $key;

        return $this;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }
}
