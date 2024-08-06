#!/usr/bin/env php
<?php

$path = __DIR__ .'/daily/';

define('NO_LOGIN', true);

require_once($path .'../../../config.php');

$db = new Db();

foreach(File::scan_dir($path) as $file){

    echo $file .' ';

    include($path .'/'. $file);

    echo ' <br />';
}

echo 'cron daily done';
