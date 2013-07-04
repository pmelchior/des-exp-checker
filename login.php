<?php
include_once('usermanagement.php');

$um = new UserManagement();

// start authentication by creating a one-time seed and return it
if (isset($_POST['send_seed'])) {
  $answer['seed'] = $um->setSeed(); 
 } 
// client return username, seed and hash = sha1(sha1(password) + seed):
// login attempt
elseif (isset($_POST['login'])) {
  if (isset($_POST['username']) && isset($_POST['hash'])) {
    $answer = $um->login($_POST['login'],$_POST['username'],$_POST['hash']);
  }
}
// logout attempt
elseif (isset($_POST['logout'])) {
  $answer = $um->logout();
}

echo json_encode($answer);

?>