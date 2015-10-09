<?php
include_once('common.php.inc');

class UserManagement {
  private $conn;
  function __construct() {
    $this->conn = getDBHandle();
  }
  function __destruct() {
    $this->conn = null;
  }
  function getHash() {
    // random has
    $time = time();
    $hash = sha1(strval(rand()).strval($time));
    return $hash;
  }
  function setSeed() {
    // create one-time seed and store it in DB
    $seed = $this->getHash();
    $query = "INSERT INTO `seeds` (`seed`) VALUES ('" . $seed ."')";
    $this->conn->query($query);
    // clean up seed table of outdated seeds
    $oldest_time = $time - 3600*24*5;
    $oldest_date = date("Y-m-d", $oldest_time);
    $query = "DELETE FROM `seeds` WHERE `timestamp` < '". $oldest_date ."'";
    $this->conn->query($query);
    return $seed;
  }
  public function login($seed,$username,$userhash) {
    global $config;
    $answer = array();
    $stmt = $this->conn->prepare("SELECT COUNT(`seed`) FROM `seeds` WHERE `seed` = ?");
    $stmt->bindParam(1, $seed, PDO::PARAM_STR, 40);
    $stmt->execute();
    //$stmt->bind_result($count);
    if ($stmt->fetch()) {
      $stmt->closeCursor();
      // found seed to be valid
      $stmt = $this->conn->prepare("SELECT `password` FROM `users` WHERE `username` = ? LIMIT 1");
      $stmt->bindParam(1, $username, PDO::PARAM_STR);
      $stmt->execute();
      if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$password = $row["password"];
	$stmt->closeCursor();
	$dbhash = sha1($password.$seed);
	if ($userhash == $dbhash) {
	  // set cookies for sid and username, valid for 30 days
	  $now = time();
	  setcookie("sid", $dbhash, $now + 3600*24*30, "/", $config['domain']);
	  
	  // set session_id to track user: username seems OK, so use simple query
	  $query = "INSERT INTO `sessions` (sid, uid, ip) VALUES ('". $dbhash ."', (SELECT rowid FROM `users` WHERE username = '". $username ."'), '" . $_SERVER['REMOTE_ADDR']."')";
	  $this->conn->query($query);

	  // delete old sessions
	  $oldest_date = date("Y-m-d", $now - 3600*24*30);
	  $query = "DELETE FROM `sessions` WHERE `timestamp` < '" . $oldest_date ."'";
	  $this->conn->query($query);
	  
	  // delete seed from DB: as seed seems to be OK, also use simple query
	  $query = "DELETE FROM `seeds` WHERE `seed` = '". $seed ."'";
	  $this->conn->query($query);
	  $answer['message'] = "OK";
	} else { // wrong password hash
	  $answer['message'] = "Login failed. Please try again";
	}
      }
      // username not in DB
      else {
	$answer['message'] = "Login failed. Please try again";
      }
    } else {
      // send error and new seed
      $answer['message'] = "Session expired. Please login again";
      $answer['seed'] = $this->setSeed();
    }
    return $answer;
  }
  public function direct_login($uid) {
    global $config;
    // create new session for a newly registered user
    // set cookies for sid and username, valid for 30 days
    $now = time();
    $sid = sha1(strval($now).$uid);
    setcookie("sid", $sid, $now + 3600*24*30, "/", $config['domain']);

    // set session_id to track user: username seems OK, so use simple query
    $query = "INSERT INTO `sessions` (sid, uid, ip) VALUES ('". $sid ."', " . $uid . ", '" . $_SERVER['REMOTE_ADDR']."')";
    $this->conn->query($query);
  }
  public function logout() {
    global $config;
    $stmt = $this->conn->prepare("DELETE FROM `sessions` WHERE sid = ?");
    if (in_array('sid',array_keys($_COOKIE))) {
      $stmt->bindParam(1, $_COOKIE['sid'], PDO::PARAM_STR, 40);
      $stmt->execute();
      $stmt->closeCursor();
      // clear cookies
      setcookie("sid", "", time() - 3600, "/", $config['domain']);
    }
  }
  public function resetPassword($email) {
    // use first 10 letters of hash as new password
    $hash = $this->getHash();
    $password = substr($hash, 0, 10);
    $pw_encrypted = sha1($password);
    $stmt = $this->conn->prepare("UPDATE `users` SET password=? WHERE email=?");
    $stmt->execute(array($pw_encrypted, $email));
    if ($stmt->rowCount()) {
      $stmt->closeCursor();
      // this was a valid email address, get username
      $stmt = $this->conn->prepare("SELECT username FROM `users` WHERE email=? LIMIT 1");
      $stmt->bindParam(1, $email, PDO::PARAM_STR);
      $stmt->execute();
      if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$row['password'] = $password;
	return $row;
      }
    } else
      return FALSE;
  }
}

?>
