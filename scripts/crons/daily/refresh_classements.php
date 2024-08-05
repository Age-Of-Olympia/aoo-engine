<?php

Player::refresh_list();

@unlink('datas/public/classements/general.html');
@unlink('datas/public/classements/bourrins.html');
@unlink('datas/public/classements/reputation.html');
@unlink('datas/public/classements/fortunes.html');


echo 'done';
