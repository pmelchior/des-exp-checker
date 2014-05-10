<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['fileid'])) {
    $problems = array();
    $res = getProblems($dbh, $_GET['fileid']);
    $codes = getProblemCodes();
    $problem_code = array_flip($codes);
    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $row['problem'] = $problem_code[$row['problem']];
        array_push($problems, $row);
    }
    echo json_encode($problems);
}

?>
