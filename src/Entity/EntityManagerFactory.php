<?php

namespace App\Entity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;

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
            self::$em = new EntityManager($connection, $orm_db_config);
        }
        return self::$em;
    }
}