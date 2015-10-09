<?php
include_once('usermanagement.php');

$um = new UserManagement();

// start authentication by creating a one-time seed and return it
if (isset($_POST['send_seed'])) {
  $answer['seed'] = $um->setSeed();
  echo json_encode($answer);
}

// client return username, seed and hash = sha1(sha1(password) + seed):
// login attempt
elseif (isset($_POST['login'])) {
  if (isset($_POST['username']) && isset($_POST['hash'])) {
    $answer = $um->login($_POST['login'],$_POST['username'],$_POST['hash']);
    echo json_encode($answer);
  }
}

// logout attempt
elseif (isset($_POST['logout'])) {
  $um->logout();
}

// reset password attempt
elseif (isset($_POST['reset_pw'])) {
  if (isset($_POST['email'])) {
    $answer = $um->resetPassword($_POST['email']);
    if ($answer) {
      // this was a valid email address:
      // get new password and username, send email
      $subject = "Password reset for DES exposure checker website";
      $message = "Dear " . $answer['username'] .",\n\n";
      $message .= "Someone, probably you, requested a password reset. ";
      $message .= "Your new password is\n\n";
      $message .= "        " . $answer['password']."\n\n";
      $message .= "Have fun,";
      sendEmail($_POST['email'], $subject, $message);
    }
    $reply = array('message' => 'OK');
    echo json_encode($reply);
  }
}
?>
