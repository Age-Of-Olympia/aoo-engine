<?php

require_once('config.php');

if (empty($_GET['targetId'])) {
    exit('error container');
}

include('scripts/container/body.php');
