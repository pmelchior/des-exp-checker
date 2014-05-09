<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['fileid'])) {
    $problems = array();
    $res = getProblems($dbh, $_GET['fileid']);
    $codes = getProblemCodes();
    $problem_code = array_flip($codes);

    while($row = $res->fetch(PDO::FETCH_ASSOC)) {
        
        $formatted = array('problem' => $problem_code[$row['problem']]);
        $detail = json_decode($row['comment'], true);
        foreach ($detail as $k => $v)
            $formatted[$k] = $v;
        array_push($problems, $formatted);
    }
    echo json_encode($problems);
}

?>
