<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\ProxyFactory;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\ORMSetup;

require_once dirname(__FILE__)."/../vendor/autoload.php";
require_once (__DIR__."/db_constants.php");

// Create a simple "default" Doctrine ORM configuration for Attributes
$orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__.'/../src/Entity'],
    isDevMode: true,
);
$orm_db_config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED);    

//initialize the EntityManager allow shared connection for our transactions
$em=EntityManagerFactory::getEntityManager();
// configuring the database connection
$connection = $em->getConnection();
global $link;
$link = $connection;