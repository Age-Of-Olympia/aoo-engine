<?php
use Classes\ActorInterface;

require_once($_SERVER['DOCUMENT_ROOT'].'/config.php');

if(!isset($_SESSION['playerId'])){
  exit();
}

// check super admin
if(!isset($_SESSION['isSuperAdmin'])){
  // check super admin
  $playerToCheck = new ActorInterface($_SESSION['playerId']);
  if(!$playerToCheck->have_option('isSuperAdmin')){
      exit('Action réservée aux super administrateurs');
  }
  else{
      $_SESSION['isSuperAdmin'] = true;
  }
}
