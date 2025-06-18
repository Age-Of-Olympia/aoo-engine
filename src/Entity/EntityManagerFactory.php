<?php

namespace App\Entity;

use App\Listener\ActionMetadataListener;
use App\Listener\OutcomeInstructionMetadataListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Proxy\ProxyFactory;

final class EntityManagerFactory
{
    private static ?EntityManager $em = null;

    public static function getEntityManager(): EntityManager
    {
        if (self::$em === null) {
            $orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
                paths: [__DIR__],
                isDevMode: false
            );
            $orm_db_config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED);
            $connection = DriverManager::getConnection(DB_CONSTANTS, $orm_db_config);
            self::$em = new EntityManager($connection, $orm_db_config);
        }
        $eventManager = self::$em->getEventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, new ActionMetadataListener());
        $eventManager->addEventListener(Events::loadClassMetadata, new OutcomeInstructionMetadataListener());
        return self::$em;
    }
}