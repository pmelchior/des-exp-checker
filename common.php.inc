<?php

include_once("config.php.inc");

function check_or_abort($dbh) {
    if (!$dbh) {
        header('HTTP/1.0 500 Internal Server Error');
        exit(0);
    }
    return $dbh;
}

function GETorPOST($param) {
    if (isset($_GET[$param]))
        return $_GET[$param];
    if (isset($_POST[$param]))
        return $_POST[$param];
    return FALSE;
}

function setRelease() {
    global $config;
    $config['release'] = GETorPOST('release');
    if ($config['release'] === FALSE || in_array($config['release'], $config['releases']) === FALSE)
        $config['release'] = array_pop($config['releases']);
}

function getDBHandle() {
    setRelease();
    global $config;
    $dbh = new PDO('sqlite:'. $config['userdb']);
    $filedbfile = $config['filedb'][$config['release']];
    if (file_exists($filedbfile)) {
        $dbh->query('ATTACH DATABASE "' . $filedbfile .'" AS files');
        return check_or_abort($dbh);
    }
    else
        check_or_abort(FALSE);
}

function getNextImage($dbh, $uid) {
    global $config;
    $res = null;
    $sql = 'SELECT "' . $config['release'] . '" as release, files.fileid, expname, ccd, band, name FROM files';

    if (isset($_POST['expname']) && isset($_POST['ccd'])) {
        $sql .= ' WHERE ccd = ? AND files.expname = ? LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $_POST['ccd'], PDO::PARAM_INT);
        $stmt->bindParam(2, $_POST['expname'], PDO::PARAM_STR, 14);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    elseif (isset($_POST['problem'])) {
        $sql .= ' JOIN qa ON (files.fileid = qa.fileid) WHERE qa.problem = ?';
        if (isset($_POST['detail']))
            $sql .= ' AND detail = ?';
        $sql .= ' ORDER BY RANDOM() LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $codes = getProblemCodes();
        $stmt->bindParam(1, $codes[$_POST['problem']], PDO::PARAM_INT);
        if (isset($_POST['detail']))
            $stmt->bindParam(2, $_POST['detail'], PDO::PARAM_STR);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    // default query
    else {
        $priority = "1";
        // to created redundancy: make every n-th image drawn from list with existing qa
        if (rand(0,2) < 1) {
            $fallback = $sql;
            $sql .= ' JOIN qa ON (files.fileid = qa.fileid)';
            if ($uid) {
                $sql .= ' WHERE ' . $priority . ' AND qa.userid != ' . $uid;
                $sql .= ' GROUP BY qa.fileid ORDER BY RANDOM() LIMIT 1';
                // need to check if there are actually images found:
                // only relevant for early stages of a new release
                $res = check_or_abort($dbh->query($sql));
                if ($row = $res->fetch(PDO::FETCH_ASSOC))
                    return $row;
            }
            $sql = $fallback;
        }
        $res = check_or_abort($dbh->query($sql.' WHERE ' . $priority . ' ORDER BY RANDOM() LIMIT 1'));
    }
    if ($row = $res->fetch(PDO::FETCH_ASSOC))
        return $row;
    else
        return FALSE;
}

function getProblems($dbh, $fileid, $qa_id=NULL) {
    global $config;
    $sql = 'SELECT problem, x, y, detail FROM qa WHERE fileid=?';
    if (isset($qa_id))
        $sql .= ' AND qaid=?';
    else
        $sql .= ' AND problem != 0 AND problem <= 1000';
    $stmt = $dbh->prepare($sql);
    $stmt->bindParam(1, $fileid, PDO::PARAM_INT);
    if (isset($qa_id))
        $stmt->bindParam(2, $qa_id, PDO::PARAM_INT);
    $stmt->execute();
    $res = check_or_abort($stmt);
    $problem_code = array_flip($config['problem_code']);
    $problems = array();
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        if ($row['problem'] > 0)
            $row['problem'] = $problem_code[$row['problem']];
        else
            $row['problem'] = "-" . $problem_code[-$row['problem']];
        $row['x'] = intval($row['x']);
        $row['y'] = intval($row['y']);
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
    if ($total_files / $config['images_per_fp'] >= 100000)
      return 8;
    if ($total_files / $config['images_per_fp'] >= 10000)
      return 7;
    if ($total_files / $config['images_per_fp'] >= 1000)
      return 6;
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
    case 4: return 100*$config['images_per_fp'] - $total_files; break;
    case 5: return 1000*$config['images_per_fp'] - $total_files; break;
    case 6: return 10000*$config['images_per_fp'] - $total_files; break;
    case 7: return 100000*$config['images_per_fp'] - $total_files; break;
    case 8: return 0; break;
  }
}

function getActivity($dbh, $uid, $date=FALSE) {
    $activity = array();
    if ($date === FALSE)
        $date = date('Y-m-d H:i:s', strtotime('-1 day'));
    $sql = "SELECT COUNT(DISTINCT(fileid)) as activity FROM qa WHERE userid=".$uid." AND timestamp > '".$date."%'";
    $res = check_or_abort($dbh->query($sql));
    $activity['today'] = array_pop($res->fetch(PDO::FETCH_NUM));
    $sql = "SELECT total_files FROM submissions WHERE userid=".$uid;
    $res = check_or_abort($dbh->query($sql));
    $activity['alltime'] = array_pop($res->fetch(PDO::FETCH_NUM));
    return $activity;
}

function giveBonusPoints($dbh, $uid, $points) {
    $stmt = $dbh->prepare("UPDATE submissions SET total_files = total_files + ? WHERE userid = ?");
    $stmt->bindParam(1, $points, PDO::PARAM_INT);
    $stmt->bindParam(2, $uid, PDO::PARAM_INT);
    check_or_abort($stmt->execute());
}

function sendEmail($email, $subject, $message) {
    global $config;
    $header = "From: " . $config['adminname'] . " <". $config['adminemail'] .">\n";
    $header .= "Reply-To: <". $config['adminemail'] .">\n";
    $header .= "Content-Type: text/plain; charset=UTF-8\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $message .= "\n\n" . $config['adminname'] ." --";
    $message .= "\nhttp://" . $config['domain'];
    return mail($email,$subject,$message,$header);
}
?>
