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
    private static ?string $database = null;

    /**
     * Point every connection at another database, for the duration of the
     * process. The legacy stack follows: `config/bootstrap.php` takes its
     * `$link` from here and `db()` hands that to `Classes\Db`.
     *
     * The test harness is the only caller. Its fixtures write real rows through
     * the production paths, so they need a database they may ruin — running
     * them against the development world leaves debris behind whenever a
     * teardown is interrupted, and that debris then breaks later runs.
     */
    public static function useDatabase(?string $name): void
    {
        if ($name === self::$database) {
            return;
        }

        self::$database = $name;
        self::$em = null; // rebuilt on the next call, against the new database
    }

    public static function currentDatabase(): string
    {
        return self::$database ?? (string) (DB_CONSTANTS['dbname'] ?? DB_CONSTANTS['db'] ?? '');
    }

    public static function getEntityManager(): EntityManager
    {
        if (self::$em === null) {
            EntityManagerFactory::InitOrmConfig();

            $params = DB_CONSTANTS;
            if (self::$database !== null) {
                $params['dbname'] = self::$database;
                $params['db'] = self::$database;
            }

            $connection = DriverManager::getConnection($params, self::$orm_db_config);

            // CRITICAL: Force UTF-8mb4 charset for migrations
            // This fixes "Data truncated" errors when inserting French characters
            $connection->executeStatement('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

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