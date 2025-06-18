<?php

namespace App\Entity;

use App\Listener\ActionMetadataListener;
use App\Listener\OutcomeInstructionMetadataListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Configuration;

final class EntityManagerFactory
{
    private static ?EntityManager $em = null;
    private static ?Configuration $orm_db_config = null;
    public static function getEntityManager(): EntityManager
    {
        if (self::$em === null) {
            EntityManagerFactory::InitOrmConfig();
            $connection = DriverManager::getConnection(DB_CONSTANTS, self::$orm_db_config);
            self::$em = new EntityManager($connection, self::$orm_db_config);
        }
        $eventManager = self::$em->getEventManager();
        $eventManager->addEventListener(Events::loadClassMetadata, new ActionMetadataListener());
        $eventManager->addEventListener(Events::loadClassMetadata, new OutcomeInstructionMetadataListener());
        return self::$em;
    }

    public static function InitOrmConfig(): void
    {
        if (self::$orm_db_config !== null) {
            return; // already initialized
        }
        $isDevMode = defined('DEV_MODE') && DEV_MODE;

        self::$orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__],
            isDevMode: $isDevMode
        );
        $proxyDir = __DIR__ . '/../../var/proxies';
        if (!is_dir($proxyDir)) {
            mkdir($proxyDir, 0755, true);
        }
        self::$orm_db_config->setProxyDir($proxyDir);
        self::$orm_db_config->setProxyNamespace('Proxies');
        self::$orm_db_config->setAutoGenerateProxyClasses(true);
    }
}