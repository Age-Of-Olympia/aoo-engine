<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if(!isset($_SESSION['playerId'])){
  echo 'login required';
  exit();
}

// check admin (only once per session)
if(!isset($_SESSION['isAdmin'])){
  // check admin
  $player = new Player($_SESSION['playerId']);
  if(!$player->have_option('isAdmin')){

      echo 'admin account required';
      exit();
  }
  else{
      $_SESSION['isAdmin'] = true;
  }
}
