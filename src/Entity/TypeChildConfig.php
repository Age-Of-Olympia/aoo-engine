<?php

namespace App\Entity;

/**
 * A row of type-scoped config edited by the type-defaults page: an instruction
 * ({@see ActionTypeInstruction}) or a precondition ({@see ActionTypePrecondition})
 * attached to an action type key (or the global empty scope). The common shape
 * lets {@see \App\Service\Action\AbstractTypeChildEditService} list/save them
 * generically; the per-kind "which condition/instruction" field stays on the
 * concrete entity.
 */
interface TypeChildConfig
{
    public function getId(): ?int;

    public function getTypeKey(): string;

    /** @return array<string, mixed>|null */
    public function getParameters(): ?array;

    /** @param array<string, mixed>|null $parameters */
    public function setParameters(?array $parameters): self;
}
