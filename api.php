<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['problem'])) {
    $codes = getProblemCodes();
    $negative = FALSE;
    if ($_GET['problem'][0] == "-") {
        $_GET['problem'] = substr($_GET['problem'], 1);
        $negative = TRUE;
    }
    if (isset($_GET['negative']))
        $negative = TRUE;
    if (array_search($_GET['problem'], array_keys($codes)) !== FALSE) {
        $code = $codes[$_GET['problem']];
        if ($negative)
            $code *= -1;
        $stm = $dbh->prepare('SELECT qa.rowid as qa_id, expname, ccd, band, problem, x, y, detail FROM qa JOIN files ON (files.rowid=qa.fileid) WHERE problem=' . $code . ' ORDER BY expname, ccd ASC');
        $stm->execute();
        $result = array();
        while($row = $stm->fetch(PDO::FETCH_ASSOC)) {
            $row['ccd'] = intval($row['ccd']);
            $row['problem'] = $_GET['problem'];
            $row['release'] = $config['release'];
            if ($code != 0) { // good exposure don't have locations
                // correct for downsampling of factor 4
                $row['x'] *= 4;
                $row['y'] *= 4;
                $row['negative'] = $negative;
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