<?php
use Classes\File;
use Classes\Ui;

require_once('config.php');

$ui = new Ui('Init');

if(!isset($_GET['perform'])){

    echo '<a href="tools.php?init&perform"><button>(re)init</button></a>';
    exit();
}


echo 'delete datas dir : players<br />';

$path = 'datas/private/players/';

$realpath = realpath($path);

File::rrmdir($realpath);


echo 'create new datas dir : players<br />';

mkdir($path, 0755, true);


if(!file_exists('config/db_constants.php')){


    exit('<font color="red">Renommez config/db_constants.php.exemple en config/db_constants.php, puis éditez le!');
}


echo 'run db/init.sql<br />';

$sql = file_get_contents('db/init.sql');

db()->multi_query($sql);


echo 'done!<br />';


echo '
<br />
The first character to register will be granted as Admin<br />
Login to this character then press ² to open the console<br />
<a href="index.php"><button>Press this button</button></a>
';
