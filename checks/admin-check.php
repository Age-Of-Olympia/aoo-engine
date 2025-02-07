<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if(!isset($_SESSION['playerId'])){
  exit();
}

// check admin (only once per session)
if(!isset($_SESSION['isAdmin'])){
  // check admin
  $playerToCheck = new Player($_SESSION['playerId']);
  if(!$playerToCheck->have_option('isAdmin')){
      exit('Action réservée aux admin');
  }
  else{
      $_SESSION['isAdmin'] = true;
  }
}
