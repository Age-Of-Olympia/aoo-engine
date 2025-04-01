<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once dirname(__FILE__)."/../vendor/autoload.php";
require_once (__DIR__."/db_constants.php");

// Create a simple "default" Doctrine ORM configuration for Attributes
$orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__.'/../src/Entity'],
    isDevMode: true,
);

// configuring the database connection
$connection = DriverManager::getConnection(DB_CONSTANTS, $orm_db_config);
