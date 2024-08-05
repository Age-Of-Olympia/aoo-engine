<?php

$path = __DIR__ .'/daily/';

foreach(File::scan_dir($path) as $file){

    echo $file .' ';

    include($path .'/'. $file);

    echo ' <br />';
}

echo 'cron daily done';
