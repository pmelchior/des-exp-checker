<?php

include_once("config.php.inc");

function check_or_abort($dbh) {
    if (!$dbh) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(0);
    }
    return $dbh;
}

function getDBHandle() {
    global $config;
    $dbh = new PDO('sqlite:'. $config['userdb']);
    $dbh->query('ATTACH DATABASE "' . $config['filedb'].'" AS files');
    return check_or_abort($dbh);
}

function getNextImage($dbh) {
    global $config;
    $res = null;
    $sql = 'SELECT '.$config['release'].'.rowid as fileid, '.$config['release'].'.expname, ccd, band, name, files.runs.runname FROM '.$config['release'].' JOIN files.runs ON ('.$config['release'].'.expname = files.runs.expname)';
    if (isset($_GET['expname']) && isset($_GET['ccd'])) {
        $sql .= ' WHERE '.$config['release'].'.expname = ? AND ccd = ? LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $_GET['expname'], PDO::PARAM_STR, 14);
        $stmt->bindParam(2, $_GET['ccd'], PDO::PARAM_INT);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    elseif (isset($_GET['problem'])) {
        $sql .= ' JOIN qa ON ('.$config['release'].'.rowid = qa.fileid) WHERE qa.problem = ? ORDER BY RANDOM() LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $codes = getProblemCodes();
        $stmt->bindParam(1, $codes[$_GET['problem']], PDO::PARAM_INT);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    // default query
    else
        $res = check_or_abort($dbh->query($sql.' ORDER BY RANDOM() LIMIT 1'));
    if ($row = $res->fetch(PDO::FETCH_ASSOC))
        return $row;
    else
        return FALSE;
}

function getProblems($dbh, $fileid, $qa_id=NULL) {
    global $config;
    $sql = 'SELECT problem, x, y, detail FROM qa WHERE release=? AND fileid=?';
    if (isset($qa_id))
        $sql .= ' AND rowid=?';
    else 
        $sql .= ' AND problem != 0 AND problem >= -1';
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(1, $config['release'], PDO::PARAM_STR, 4);
    $stmt->bindParam(2, $fileid, PDO::PARAM_INT);
    if (isset($qa_id))
        $stmt->bindParam(3, $qa_id, PDO::PARAM_INT);
    $stmt->execute();
    $res = check_or_abort($stmt);
    $codes = getProblemCodes();
    $problem_code = array_flip($codes);
    $problems = array();
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $row['problem'] = $problem_code[$row['problem']];
        array_push($problems, $row);
    }
    return $problems;
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

function numberSuffix($num) {
    switch($num) {
        case 1: return '1st'; break;
        case 2: return '2nd'; break;
        default: return $num.'th'; break; 
    }
}

function userClass($total_files) {
    global $config;
    if ($total_files / $config['images_per_fp'] >= 100)
      return 5;
    if ($total_files / $config['images_per_fp'] >= 10)
      return 4;
    if ($total_files / $config['images_per_fp'] >= 5)
      return 3;
    if ($total_files / $config['images_per_fp'] >= 1)
      return 2;
    if ($total_files >= 10)
      return 1;
    return 0;
}

function missingFilesForNextClass($total_files, $userclass) {
    global $config;
    switch($userclass) {
      case 0: return 10 - $total_files; break;
      case 1: return $config['images_per_fp'] - $total_files; break;
      case 2: return 5*$config['images_per_fp'] - $total_files; break;
      case 3: return 10*$config['images_per_fp'] - $total_files; break;
      case 4: return 100*$config['mages_per_fp'] - $total_files; break;
      case 5: return 1000*$config['mages_per_fp'] - $total_files; break;
    }
  }
  
function getUserData($dbh, $uid) {
    $stmt = $dbh->prepare("SELECT username, total_files, flagged_files, (SELECT COUNT(*)+1 from users WHERE total_files > (SELECT total_files FROM users WHERE rowid = ?)) as rank FROM  users WHERE rowid = ?");
    $stmt->bindParam(1, $uid, PDO::PARAM_INT);
    $stmt->bindParam(2, $uid, PDO::PARAM_INT);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['userclass'] = userClass($row['total_files']);
        $row['missingfiles'] = missingFilesForNextClass($row['total_files'], $row['userclass']);
        return $row;
    }
    else {
        return FALSE;
    }
}

function getActivity($dbh, $uid, $date=FALSE) {
    $activity = array();
    if ($date === FALSE)
        $date = date('Y-m-d H:i:s', strtotime('-1 day'));
    $sql = "SELECT COUNT(DISTINCT(fileid)) as activity FROM qa WHERE userid=".$uid." AND timestamp > '".$date."%'";
    $res = check_or_abort($dbh->query($sql));
    $activity['today'] = array_pop($res->fetch(PDO::FETCH_NUM));
    $sql = "SELECT total_files FROM users WHERE rowid=".$uid;
    $res = check_or_abort($dbh->query($sql));
    $activity['alltime'] = array_pop($res->fetch(PDO::FETCH_NUM));
    return $activity;
}

function giveBonusPoints($dbh, $uid, $points) {
    $stmt = $dbh->prepare("UPDATE users SET total_files = total_files + ? WHERE rowid = ?");
    $stmt->bindParam(1, $points, PDO::PARAM_INT);
    $stmt->bindParam(2, $uid, PDO::PARAM_INT);
    check_or_abort($stmt->execute());
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
      
      // Temporary categories, to be re-inspected
      "Old_Readout" => -5,
      "Old_Other" => -6,
      "Old_Sky" => -7,
      
      // Instrument
      "Guiding" => 11,
      "Shutter" => 12,
      "Readout" => 13,
      "Haze" => 14,
      
      // Flat fielding
      "A/B jump" => 21,
      "Fringing" => 22,
      "Tape bump" => 23,
      "Tree rings" => 24,
      "Vertical jump" => 25,
      "Vertical stripes" => 26,
      
      // Reflections
      "Ghost" => 32,
      "Bright spray" => 31,
      "Brush strokes" => 33,
      "Bright arc" => 34,
      
      // Tracks
      "Satellite" => 41,
      "Airplane" => 42,
      
      // Masking
      "Column mask" => 51,
      // "Mask dots" => 52, // found unneccesary
      "Excessive mask" => 53,
      "Cosmic ray" => 54,
      "Cross-talk" => 55,
      "Edge-bleed" => 56,
      
      // Sky estimation
      "Dark rim" => 61,
      "Dark halo" => 62,
      "Quilted sky" => 63,
      "Wavy sky" => 64,
      "Anti-bleed" => 65,
      
      "Other..." => 255,
      "Awesome!" => -1,
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
