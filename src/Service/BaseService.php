<?php

namespace App\Service;

use App\Interface\AuditableInterface;

abstract class BaseService implements AuditableInterface
{
    protected $auditService;
    protected ?int $currentAuditKey;

    public function __construct()
    {
        $this->auditService = new AuditService;
        $this->currentAuditKey = null;
    }

    public function addAuditLog(?string $details = null): void
    {
        $this->auditService->setCurrentAuditKey($this->currentAuditKey);
        $this->auditService->addAuditLog($details);
    }

    protected function executeAndLog(callable $action): bool
    {
        $res = $action();
        $this->addAuditLog(get_class($this), "Execute And Log has been called");
        return $res;
    }

    public function setCurrentAuditKey(?int $currentKey): self {
      $this->currentAuditKey = $currentKey;

      return $this;
    }


}