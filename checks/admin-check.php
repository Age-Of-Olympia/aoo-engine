<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if(!isset($_SESSION['playerId'])){
  exit();
}

// check admin (only once per session)
if(!isset($_SESSION['isAdmin'])){
  // check admin
  $player = new Player($_SESSION['playerId']);
  if(!$player->have_option('isAdmin')){
      exit();
  }
  else{
      $_SESSION['isAdmin'] = true;
  }
}
