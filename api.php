<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['problem'])) {
    $codes = getProblemCodes();
    if (array_search($_GET['problem'], array_keys($codes)) !== FALSE) {
        $code = $codes[$_GET['problem']];
        $stm = $dbh->prepare("SELECT expname, ccd, problem, comment as location FROM qa JOIN files ON (files.files.rowid=qa.fileid) WHERE problem=" . $code . " ORDER BY expname, ccd ASC");
        $stm->execute();
        $result = array();
        while($row = $stm->fetch(PDO::FETCH_ASSOC)) {
            $row['ccd'] = intval($row['ccd']);
            $row['problem'] = $_GET['problem'];
            if ($code != 0) { // good exposure don't have locations
                $row['location'] = json_decode($row['location']);
                // correct for downsampling of factor 4
                $row['location']->x *= 4;
                $row['location']->y *= 4;
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