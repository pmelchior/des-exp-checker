<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['problem'])) {
    $codes = getProblemCodes();
    if (array_search($_GET['problem'], array_keys($codes)) !== FALSE) {
        $code = $codes[$_GET['problem']];
        $stm = $dbh->prepare('SELECT qa.qaid as qa_id, expname, ccd, band, problem, x, y, detail FROM qa JOIN files ON (files.fileid=qa.fileid) WHERE problem=' . $code . ' OR problem=-' . $code . ' ORDER BY expname, ccd ASC');
        $stm->execute();
        $result = array();
        while($row = $stm->fetch(PDO::FETCH_ASSOC)) {
            $row['ccd'] = intval($row['ccd']);
            $row['false_positive'] = FALSE;
            if ($row['problem'] < 0)
                $row['false_positive'] = TRUE;
            $row['problem'] = $_GET['problem'];
            $row['release'] = $config['release'];
            if ($code != 0) { // good exposure don't have locations
                // correct for downsampling of factor 4
                $row['x'] *= 4;
                $row['y'] *= 4;
            }
            array_push($result, $row);
        }
        echo json_encode($result);
    }
    else {
        echo "Problem unknown!";
    }
}
?>