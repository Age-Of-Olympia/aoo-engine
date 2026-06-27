<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The log-message templates attached to an action *type* (e.g. "attack",
 * "technique") rather than a single action — the data-driven replacement for the
 * getLogMessages() methods that used to be hardcoded per Action subclass.
 *
 * An action inherits the template of the closest type in its class ancestry that
 * has a row (a SpellAction has no "spell" row, so it falls back to "technique").
 * Templates support the placeholders {actor}, {target}, {action} and {weapon};
 * see {@see \App\Service\Action\ActionLogResolver}.
 */
#[ORM\Entity]
#[ORM\Table(name: "action_type_logs")]
class ActionTypeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 100, name: "type_key", unique: true)]
    private string $typeKey;

    /** Template for the actor's log line; null/empty = no line. */
    #[ORM\Column(type: "text", name: "actor_template", nullable: true)]
    private ?string $actorTemplate = null;

    /** Template for the target's log line; null/empty = no line. */
    #[ORM\Column(type: "text", name: "target_template", nullable: true)]
    private ?string $targetTemplate = null;

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

    public function getActorTemplate(): ?string
    {
        return $this->actorTemplate;
    }

    public function setActorTemplate(?string $actorTemplate): self
    {
        $this->actorTemplate = $actorTemplate;
        return $this;
    }

    public function getTargetTemplate(): ?string
    {
        return $this->targetTemplate;
    }

    public function setTargetTemplate(?string $targetTemplate): self
    {
        $this->targetTemplate = $targetTemplate;
        return $this;
    }
}
