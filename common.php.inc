<?php

include_once("config.php.inc");

function getDBHandle() {
    global $config;
    $dbh = new PDO('sqlite:'. $config['userdb']);
    $dbh->query('ATTACH DATABASE "' . $config['filedb'].'" AS files');
    if (!$dbh) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(0);
    }
    return $dbh;
}

function getNextImage($dbh) {
    $sql = 'SELECT files.files.rowid, expname, ccd, band, name FROM files.files';
    if (isset($_GET['expname']) && isset($_GET['ccd'])) {
        $sql .= ' WHERE expname = ? AND ccd = ? LIMIT 1;';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $_GET['expname'], PDO::PARAM_STR, 14);
        $stmt->bindParam(2, $_GET['ccd'], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
    if (isset($_GET['problem'])) {
        $sql .= ' JOIN qa ON (files.rowid = qa.fileid) WHERE qa.problem = ? ORDER BY RANDOM() LIMIT 1;';
        $stmt = $dbh->prepare($sql);
        $codes = getProblemCodes();
        $stmt->bindParam(1, $codes[$_GET['problem']], PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
    return $dbh->query($sql.' ORDER BY RANDOM() LIMIT 1');
}

function getUIDFromSID($dbh) {
  global $config;
  if (in_array('sid',array_keys($_COOKIE))) {
    $stmt = $dbh->prepare("SELECT uid FROM `sessions` WHERE sid = ?");
    $stmt->bindParam(1, $_COOKIE['sid'], PDO::PARAM_STR, 40);
    $stmt->execute();
    $result = FALSE;
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC))
      $result = $row['uid'];
    else // CAVEAT: this can be used to guess SID by polling the server with a crafted cookie
       setcookie("sid", "", time() - 3600, "/", $config['domain']);
    $stmt->closeCursor();
    return $result;
  } else
    return FALSE;
}

function getUserData($dbh, $uid) {
    $stmt = $dbh->prepare("SELECT username, total_files, flagged_files, (SELECT COUNT(*)+1 from users WHERE total_files > (SELECT total_files FROM users WHERE rowid = ?)) as rank FROM  users WHERE rowid = ?");
    $stmt->bindParam(1, $uid, PDO::PARAM_INT);
    $stmt->bindParam(2, $uid, PDO::PARAM_INT);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        return $row;
    else {
        return FALSE;
    }
}

// mimics json formatting
function json_output($data) {
    if (gettype($data) == "string")
        return "\"". $data . "\"";
    if (gettype($data) == "array") {
        $chunks = array();
        // from http://stackoverflow.com/questions/5996749/determine-whether-an-array-is-associative-hash-or-not
        // finding a indexed = non-associative array
        if (array_keys($data) === range(0, count($data) - 1)) {
            foreach ($data as $d)
                array_push($chunks, json_output($d));
            return "[" . implode(",", $chunks) . "]";
        } else {
            foreach ($data as $k => $v)
                array_push($chunks, "\"" . $k . "\":" . json_output($v));
            return "{" . implode(",", $chunks) . "}";
        }
    }
    else return $data;
}

function getProblemCodes() {
    return array(
      "OK" => 0,
      "Cosmic ray" => 1,
      "Ghost" => 2,
      "Cross-talk" => 3,
      "Satellite" => 4,
      "Readout" => 5,
      "Failure" => 128,
      "Other..." => 255
    );
}

function sendEmail($email, $subject, $message) {
    global $config;
    $header = "From: DES exposure checker <". $config['adminemail'] .">\n";
    $header .= "Reply-To: <". $config['adminemail'] .">\n";
    $header .= "Content-Type: text/plain; charset=UTF-8\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $message .= "\n\nPeter & Erin --\nhttp://" . $config['domain'];
    return mail($email,$subject,$message,$header);
}
?>
