<?php
use Classes\Player;
ActorInterface::refresh_list();

@unlink(__DIR__ .'/../../../datas/public/classements/general.html');
@unlink(__DIR__ .'/../../../datas/public/classements/bourrins.html');
@unlink(__DIR__ .'/../../../datas/public/classements/reputation.html');
@unlink(__DIR__ .'/../../../datas/public/classements/fortunes.html');


echo 'done';
