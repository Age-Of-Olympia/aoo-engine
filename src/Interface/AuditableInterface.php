<?php

namespace App\Interface;

interface AuditableInterface
{
    public function addAuditLog(?string $details = null): void;

    public function setCurrentAuditKey(?int $currentKey): self;
}