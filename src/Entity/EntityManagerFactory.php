<?php

namespace App\Entity;

use App\Listener\ActionMetadataListener;
use App\Listener\OutcomeInstructionMetadataListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;

final class EntityManagerFactory
{
    private static ?EntityManager $em = null;

    public static function getEntityManager(): EntityManager
    {
        if (self::$em === null) {
            $orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
                paths: [dirname(__FILE__)],
                isDevMode: false
            );
            $connection = DriverManager::getConnection(DB_CONSTANTS, $orm_db_config);

            // CRITICAL: Force UTF-8mb4 charset for migrations
            // This fixes "Data truncated" errors when inserting French characters
            $connection->executeStatement('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

            self::$em = new EntityManager($connection, $orm_db_config);
        }
        $eventManager = self::$em->getEventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, new ActionMetadataListener());
        $eventManager->addEventListener(Events::loadClassMetadata, new OutcomeInstructionMetadataListener());
        return self::$em;
    }
}