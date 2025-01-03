<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once $_SERVER['DOCUMENT_ROOT']."/vendor/autoload.php";

// Create a simple "default" Doctrine ORM configuration for Attributes
$orm_db_config = ORMSetup::createAttributeMetadataConfiguration(
    paths: [__DIR__ . '/config'],
    isDevMode: false,
);

// configuring the database connection
$connection = DriverManager::getConnection(DB_CONSTANTS, $orm_db_config);
