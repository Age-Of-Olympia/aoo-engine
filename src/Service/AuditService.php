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


    public function addAuditLog(?string $details): int
    {
        $audit = new Audit();
        $bt = debug_backtrace(2,1);
        if(isset($bt[0]) && isset($bt[0]['file']) && isset($bt[0]['line'])){
            $bt[0]['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $bt[0]['file']);
            $audit->setAction($bt[0]['file'].":".$bt[0]['line']);
        }
        else{
            $audit->setAction("Unknown");
        }
        
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
