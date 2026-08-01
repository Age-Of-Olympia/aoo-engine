<?php

namespace App\Service\Action;

use App\Entity\ActionPassive;
use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Create an empty passive (the rest of its fields are filled in via the
 * workbench). Sets sensible defaults for the NOT NULL columns.
 */
final class ActionPassiveCreateService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    public function create(string $name): ActionPassive
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Le nom du passif est requis.');
        }

        $passive = new ActionPassive();
        $passive->setName($name);
        $passive->setDisplayName($name);
        $passive->setType('att');
        $passive->setCarac('');
        $passive->setValue(0.0);
        $passive->setLevel(1);
        $passive->setRace('');
        $passive->setText('');
        $passive->setTraits([]);

        $this->entityManager->persist($passive);
        $this->entityManager->flush();

        return $passive;
    }
}
