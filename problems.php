<?php

include "common.php.inc";
$dbh = getDBHandle();

if (isset($_GET['fileid'])) {
    $problems = getProblems($dbh, $_GET['fileid']);
    echo json_encode($problems);
}

?>
