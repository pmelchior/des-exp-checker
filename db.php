<?php

include "common.php.inc";
$dbh = getDBHandle();

// check if POST data is present
if ($_POST) {
    $uid = getUIDFromSID($dbh);
    if ($uid) {
        // parse POST data and store each element in table qa
        $sth = $dbh->prepare('INSERT INTO qa (fileid, userid, problem, comment) VALUES (?, ?, ?, ?)');
        if (isset($_POST['problems'])) {
            $codes = getProblemCodes();
            foreach ($_POST['problems'] as $problem) {
                $code = $codes[$problem['problem']];
                $problem['x'] = (int) $problem['x'];
                $problem['y'] = (int) $problem['y'];
                unset($problem['problem']);
                if ($problem['detail'] == '')
                    unset($problem['detail']);
                // stores x,y, and (occasionally a free-form comment)
                $sth->execute(array($_POST['fileid'], $uid, $code, json_encode($problem)));
            }
            // update attached user database to reflect user action
            $sth2 = $dbh->prepare('UPDATE users SET total_files = total_files + 1, flagged_files = flagged_files + 1 WHERE rowid = ?');
            $sth2->execute(array($uid));
        } else {
            $sth->execute(array($_POST['fileid'], $uid, 0, null));
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
    
    // advent calendar
    if ($activity['today'] == 10) {
        giveBonusPoints($dbh, $uid, 10);
        if (isset($row['congrats'])) {
            $row['congrats']['detail'] .= "<br>You have also just finished your <strong>10th image today</strong>. And since it is Advent, and Advent is the time to be generous, we give you <span class='label label-important'>+10</span> points to your account.";
        } else 
            $row['congrats'] = array('text'=> "You have just finished your <strong>10th image today</strong>!",
                                    'detail' => "It is Advent. And since Advent is the time to be generous, we give you <span class='label label-important'>+10</span> points to your account."
                                    );
    }
}
echo json_encode($row);

?>
