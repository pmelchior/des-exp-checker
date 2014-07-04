<?php

include_once("config.php.inc");
/* config only defines  the variable $config:
$config = array(
    "adminemail" => T_STRING,   // email address used for contact and internal emails
    "adminname" => T_STRING,    // name used for internal emails
    "domain" => T_STRING,       // webserver domain name
    "userdb" => T_STRING,       // path to the user sqlite database
    "filedb" => T_SRING,        // path for file database, should contain '%s' to be replace by release
    "fovpath" => T_ARRAY,       // path to FoV images, one entry per release
    "release" => NULL,          // empty, set by setRelease()
    "images_per_fp" => T_INT,   // size of focal plane, used for congrats
    "fitspath" => T_ARRAY,      // path to get the fits files, one entry per release
    "fovpath" => T_ARRAY        // path to FoV images, one entry per release
);
*/

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
    if ($config['release'] === FALSE || in_array($config['release'], array('SVA1', 'Y1A1')) === FALSE)
        $config['release'] = "SVA1";
}

function getDBHandle() {
    setRelease();
    global $config;
    $dbh = new PDO('sqlite:'. $config['userdb']);
    $filedbfile = str_replace('%s', $config['release'], $config['filedb']);
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
    $sql = 'SELECT "' . $config['release'] . '" as release, files.fileid, files.expname, ccd, band, name, files.runs.runname FROM files JOIN files.runs ON (files.expname = files.runs.expname)';
    $expname = GETorPOST('expname');
    $ccd = GETorPOST('ccd');
    if ($expname && $ccd) {
        $sql .= ' WHERE ccd = ? AND files.expname = ? LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $ccd, PDO::PARAM_INT);
        $stmt->bindParam(2, $expname, PDO::PARAM_STR, 14);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    elseif (isset($_GET['problem'])) {
        $sql .= ' JOIN qa ON (files.fileid = qa.fileid) WHERE qa.problem = ?';
        if (isset($_GET['detail']))
            $sql .= ' AND detail = ?';
        $sql .= ' ORDER BY RANDOM() LIMIT 1';
        $stmt = $dbh->prepare($sql);
        $codes = getProblemCodes();
        $stmt->bindParam(1, $codes[$_GET['problem']], PDO::PARAM_INT);
        if (isset($_GET['detail']))
            $stmt->bindParam(2, $_GET['detail'], PDO::PARAM_STR);
        $stmt->execute();
        $res = check_or_abort($stmt);
    }
    // default query
    else {
        // to created redundancy: make every n-th image drawn from list with existing qa
        if (rand(0,2) < 1) {
            $fallback = $sql;
            $sql .= ' JOIN qa ON (files.fileid = qa.fileid)';
            if ($uid) {
                $sql .= ' WHERE qa.userid != ' . $uid;
                $sql .= ' GROUP BY qa.fileid ORDER BY COUNT(distinct(qa.userid)) ASC, RANDOM() LIMIT 1';
                // need to check if there are actually images found:
                // only relevant for early stages of a new release
                $res = check_or_abort($dbh->query($sql));
                if ($row = $res->fetch(PDO::FETCH_ASSOC))
                    return $row;
                else
                    $sql = $fallback;
            }
        }
        $res = check_or_abort($dbh->query($sql.' ORDER BY RANDOM() LIMIT 1'));
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
    $codes = getProblemCodes();
    $problem_code = array_flip($codes);
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

function getProblemCodes() {
    return array(
      "OK" => 0,
      
      // Temporary categories, to be re-inspected
      "Old_Readout" => 1005,
      "Old_Other" => 1006,
      "Old_Sky" => 1007,
      "Mask dots" => 1008, // found unneccesary
      
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
      "Awesome!" => 1000,
    );
}

function sendEmail($email, $subject, $message) {
    global $config;
    $header = "From: DES exposure checker <". $config['adminemail'] .">\n";
    $header .= "Reply-To: <". $config['adminemail'] .">\n";
    $header .= "Content-Type: text/plain; charset=UTF-8\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $message .= "\n\nPeter, Erin & Alex --\nhttp://" . $config['domain'];
    return mail($email,$subject,$message,$header);
}
?>
