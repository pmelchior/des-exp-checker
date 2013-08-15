<?php

include "common.php.inc";
$dbh = getDBHandle();

function createAccount($dbh, $username, $email, $password) {
  $answer = array();
  $sth = $dbh->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
  if ($sth->execute(array($username, $email, $password))) {
    $answer["success"] = TRUE;
    $answer["message"] = "<strong>Hurray!</strong> Your account has been successfully created...";
    $uid = $dbh->lastInsertId();
    
    // initiate session
    include ("usermanagement.php");
    $um = new UserManagement();
    $um->direct_login($uid);
  }
  else {
    $answer["success"] = FALSE;
    $error = $dbh->errorInfo();
    if (strpos($error[2], 'username')) {
      $answer["message"] = "The username you chose already exists. Please pick another one.";
    }
    elseif (strpos($stmt->error, 'email')) {
      $answer["message"] = "The email address your provided is already in use by a registered user. Please choose another one.";
    }
    else {
      global $config;
      $answer["message"] = "<strong>Dammit!</strong> An error occured.<br /> But don't worry, your request has been recorded and will be processed as soon as possible.";
      
      // send mail to admin
      $subject = "DES exposure checker: Account creation failed";
      $mmessage = print_r(compact('username', 'email', 'password', 'error'),true);
      $email = $config['adminemail'];
      $header = "From: " . $config['adminname']. " <". $email .">\n";
      $header .= "Reply-To: <". $email .">\n";
      $header .= "Content-Type: text/plain; charset=UTF-8\n";
      $header .= "Content-Transfer-Encoding: 8bit\n";
      mail($config['adminemail'],$subject,$mmessage,$header);
    }   
  }
  return $answer;
}

// create account
if ($_POST['action'] == "signup" && (isset($_POST['username']) && isset($_POST['email']) && isset($_POST['hash']))) {
  $answer = createAccount($dbh,$_POST['username'],$_POST['email'],$_POST['hash']);

  // send confirmation email 
  if ($answer['success'] === TRUE) {
      $email = $config['adminemail'];
      $header = "From: " . $config['adminname']. " <". $email .">\n";
      $header .= "Reply-To: <". $email .">\n";
      $header .= "Content-Type: text/plain; charset=UTF-8\n";
      $header .= "Content-Transfer-Encoding: 8bit\n";
      $subject = "Welcome to the DES exposure checker website";
      $message = "Dear " . array_shift(split(" ",$_POST['name'])) . " (" . $_POST['username'] ."),\n\n";
      $message .= "Thanks for volunteering as an early contributor to this new webapp. ";
      $message .= "Some problems are still possible, but most of the pieces should be in place. ";
      $message .= "We are very interested in the experience you have as user, ";
      $message .= "so please tell us if you like it, if something isn't working as expected, ";
      $message .= "how we can improve it, what feature we should add ...\n\n";
      $message .= "Peter & Erin --\nhttp://" . $config['domain'];
      mail($_POST['email'],$subject,$message,$header);
}
  echo json_encode($answer);
}
?>
