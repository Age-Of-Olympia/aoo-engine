#!/usr/bin/env php
<?php

if(!defined('NO_LOGIN')){
    define('NO_LOGIN', true);
}

require_once(__DIR__ . '/../../config.php');
use App\Service\CronService;

if (isset($argv[1])) {
    $cronService = new CronService();
    $cronService->executeCron($argv[1]);
}


