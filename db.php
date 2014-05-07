<?php

include "common.php.inc";
$dbh = getDBHandle();

// check if POST data is present
if ($_POST) {
    $uid = getUIDFromSID($dbh);
    if ($uid) {
        // parse POST data and store each element in table qa
        $sth = $dbh->prepare('INSERT INTO qa (release, fileid, userid, problem, catastrophic, comment) VALUES (?, ?, ?, ?, ?, ?)');
        if (isset($_POST['problems'])) {
            $codes = getProblemCodes();
            foreach ($_POST['problems'] as $problem) {
                $code = $codes[$problem['problem']];
                $problem['x'] = (int) $problem['x'];
                $problem['y'] = (int) $problem['y'];
                // FIXME: need catastropic from POST
                $catastrophic = 0;
                unset($problem['problem']);
                if ($problem['detail'] == '')
                    unset($problem['detail']);
                // stores x,y, and (occasionally a free-form comment)
                $sth->execute(array($config['release'], $_POST['fileid'], $uid, $code, $catastrophic, json_encode($problem)));
            }
            // update attached user database to reflect user action
            $sth2 = $dbh->prepare('UPDATE users SET total_files = total_files + 1, flagged_files = flagged_files + 1 WHERE rowid = ?');
            $sth2->execute(array($uid));
        } else {
            $sth->execute(array($config['release'], $_POST['fileid'], $uid, 0, 0, null));
            $sth2 = $dbh->prepare('UPDATE users SET total_files = total_files + 1 WHERE rowid = ?');
            $sth2->execute(array($uid));
        }
        $activity = getActivity($dbh, $uid);
    }
}

// return the next image
$res = getNextImage($dbh);
$row = $res->fetch(PDO::FETCH_ASSOC);
$row['name'] = "getImage.php?name=".$row['name'];


if ($_POST) {
    // badge of honor:
    $uc = userClass($activity['alltime']);
    $old_uc = userClass($activity['alltime']-1);
    if ($uc > $old_uc) {
        $row['congrats'] = array('text'=> "You have just finished your ",
                                'detail' => "To reflect your achievements, we've upgraded you to <span id='status_class' class='badge'></span> status.",
                                'userclass' => $uc
                                );
        if($uc == 1)
            $row['congrats']['text'] .= "<strong>first 10 images</strong>!";
        else {
            $fps = $activity['alltime'] / $config['images_per_fp'];
            $row['congrats']['text'] .= "<strong>". numberSuffix($fps). " focal plane</strong>!";
        }
    }
    elseif ($activity['alltime'] % $config['images_per_fp'] == 0) {
        $fps = $activity['alltime'] / $config['images_per_fp'];
        $row['congrats'] = array('text'=> "You have just finished your <strong>". numberSuffix($fps). " focal plane</strong>!");
    }
}
echo json_encode($row);

?>
