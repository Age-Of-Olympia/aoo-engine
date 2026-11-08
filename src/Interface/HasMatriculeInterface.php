<?php

namespace App\Interface;

/**
 * A `players` row as both the legacy Player and the Doctrine GameEntity
 * see it: the id the console takes and the matricule players see.
 */
interface HasMatriculeInterface
{
    public function getId(): ?int;
    public function getDisplayId(): int;
}
