<?php

namespace App\Service;

use App\Entity\Audit;
use App\Entity\EntityManagerFactory;

class AuditService
{
    private $entityManager;
    private ?int $currentAuditKey;

    public function __construct()
    {
      $this->entityManager = EntityManagerFactory::getEntityManager();
      $this->currentAuditKey = null;
    }

    public function addAuditLog(string $details = null): int
    {
        $audit = new Audit();
        $audit->setAction(__FILE__.":".__LINE__);
        $audit->setTimestamp(new \DateTime());
        if(isset($_SESSION['playerId'])){
          $audit->setUserId($_SESSION['playerId']);
        }
        if(isset($_SERVER['REMOTE_ADDR'])){
            $audit->setIpAddress($_SERVER['REMOTE_ADDR']);
        }
        $audit->setDetails($details);

        if ($this->currentAuditKey != null) {
            $audit->setAuditKey($this->currentAuditKey);
        }

        $this->entityManager->persist($audit);
        $this->entityManager->flush();

        if ($this->currentAuditKey == null) {
            $this->setCurrentAuditKey($audit->getId());
            $audit->setAuditKey($this->currentAuditKey);
            $this->entityManager->persist($audit);
            $this->entityManager->flush();
        }

        return $audit->getId();
        
    }

    public function getCurrentAuditKey(): ?int {
        return $this->currentAuditKey;
    }

    public function setCurrentAuditKey(?int $auditKey): self {
        $this->currentAuditKey = $auditKey;

        return $this;
    }
}
