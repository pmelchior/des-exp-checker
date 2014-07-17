<?php

include "common.php.inc";
$dbh = getDBHandle();
$uid = getUIDFromSID($dbh);

// check if problem marks are present
if ($uid && isset($_POST['fileid']) && $_POST['fileid'] != '') {
    // parse POST data and store each element in table qa
    $sth = $dbh->prepare('INSERT INTO qa (fileid, userid, problem, x, y, detail) VALUES (?, ?, ?, ?, ?, ?)');
    if (isset($_POST['problems'])) {
        $codes = getProblemCodes();
        foreach ($_POST['problems'] as $problem) {
            if ($problem['problem'][0] == "-") {
                $problem['problem'] = substr($problem['problem'], 1);
                $code = -$codes[$problem['problem']];
            }
            else 
                $code = $codes[$problem['problem']];
            $problem['x'] = (int) $problem['x'];
            $problem['y'] = (int) $problem['y'];
            if ($problem['detail'] == '')
               $problem['detail'] = null;
            // stores x,y, and (occasionally a free-form comment)
            $sth->execute(array($_POST['fileid'], $uid, $code, $problem['x'], $problem['y'], $problem['detail']));
        }
        // update attached user database to reflect user action
        $sth2 = $dbh->prepare('UPDATE submissions SET total_files = total_files + 1, flagged_files = flagged_files + 1 WHERE userid = ? AND release= ?');
        $sth2->execute(array($uid, $config['release']));
        // check whether the user/release already existed in submissions
        if ($sth2->rowCount() == 0) {
            $sth2 = $dbh->prepare('INSERT INTO submissions VALUES (?, ?, 1, 1)');
            $sth2->execute(array($uid, $config['release']));
        }
    } else {
        $sth->execute(array($_POST['fileid'], $uid, 0, null, null, null));
        $sth2 = $dbh->prepare('UPDATE submissions SET total_files = total_files + 1 WHERE userid = ? AND release = ?');
        $sth2->execute(array($uid, $config['release']));
        if ($sth2->rowCount() == 0) {
            $sth2 = $dbh->prepare('INSERT INTO submissions VALUES (?, ?, 1, 0)');
            $sth2->execute(array($uid, $config['release']));
        }
    }
    $activity = getActivity($dbh, $uid);
    
    // badge of honor:
    $uc = userClass($activity['alltime']);
    $old_uc = userClass($activity['alltime']-1);
    if ($uc > $old_uc) {
        $congrats = array('text'=> "You have just finished your ",
                                'detail' => "To reflect your achievements, we've upgraded you to <span id='status_class' class='badge'></span> status.",
                                'userclass' => $uc
                                );
        if($uc == 1)
            $congrats['text'] .= "<strong>first 10 images</strong>!";
        else {
            $fps = $activity['alltime'] / $config['images_per_fp'];
            $congrats['text'] .= "<strong>". numberSuffix($fps). " focal plane</strong>!";
        }
    }
    elseif ($activity['alltime'] % $config['images_per_fp'] == 0) {
        $fps = $activity['alltime'] / $config['images_per_fp'];
        $congrats = array('text'=> "You have just finished your <strong>". numberSuffix($fps). " focal plane</strong>!");
    }
}

// return the next image
$row = getNextImage($dbh, $uid);
if ($row) {
    $row['name'] = "getImage.php?release=".$config['release']."&name=".$row['name'];
    // problem marks are requested
    if(isset($_POST['show_marks']) || isset($_POST['qa_id']))
        $row['marks'] = getProblems($dbh, $row['fileid'], $_POST['qa_id']);
}
else {
    $row['error'] = "File missing";
    $row['message'] = "The requested image cannot be retrieved.";
    $row['description'] = "Either we don't have the band or the file is not part of the requested release.";
}
if (isset($congrats))
    $row['congrats'] = $congrats;

echo json_encode($row);

?>
