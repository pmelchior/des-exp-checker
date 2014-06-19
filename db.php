<?php

include "common.php.inc";
$dbh = getDBHandle();

// check if POST data is present
if ($_POST) {
    $uid = getUIDFromSID($dbh);
    if ($uid && isset($_POST['fileid']) && $_POST['fileid'] != '') {
        // parse POST data and store each element in table qa
        $sth = $dbh->prepare('INSERT INTO qa (release, fileid, userid, problem, x, y, detail) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (isset($_POST['problems'])) {
            $codes = getProblemCodes();
            foreach ($_POST['problems'] as $problem) {
                $code = $codes[$problem['problem']];
                $problem['x'] = (int) $problem['x'];
                $problem['y'] = (int) $problem['y'];
                if ($problem['detail'] == '')
                   $problem['detail'] = null;
                // stores x,y, and (occasionally a free-form comment)
                $sth->execute(array($config['release'], $_POST['fileid'], $uid, $code, $problem['x'], $problem['y'], $problem['detail']));
            }
            // update attached user database to reflect user action
            $sth2 = $dbh->prepare('UPDATE users SET total_files = total_files + 1, flagged_files = flagged_files + 1 WHERE rowid = ?');
            $sth2->execute(array($uid));
        } else {
            $sth->execute(array($config['release'], $_POST['fileid'], $uid, 0, null, null, null));
            $sth2 = $dbh->prepare('UPDATE users SET total_files = total_files + 1 WHERE rowid = ?');
            $sth2->execute(array($uid));
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
}

// return the next image
$row = getNextImage($dbh);
if ($row) {
    $row['name'] = "getImage.php?name=".$row['name'];
    // problem marks are requested
    if(isset($_GET['show_marks']) || isset($_GET['qa_id']))
        $row['marks'] = getProblems($dbh, $row['fileid'], $_GET['qa_id']);
}
else {
    $row['error'] = "File missing";
    $row['message'] = "The requested image cannot be retrieved.";
    $row['description'] = "This is likely caused by our catalog only comprising SV-A1 images in griz bands.";
}
if (isset($congrats))
    $row['congrats'] = $congrats;

echo json_encode($row);

?>
