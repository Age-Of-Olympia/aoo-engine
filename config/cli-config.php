<?php

use App\Entity\EntityManagerFactory;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\Migrations\DependencyFactory;

include (__DIR__."/bootstrap.php");

$config = new JsonFile(__DIR__."/migrations.json");

$entityManager = EntityManagerFactory::getEntityManager();

return DependencyFactory::fromEntityManager($config, new ExistingEntityManager($entityManager));