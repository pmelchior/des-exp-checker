<?php

include "common.php.inc";
$dbh = getDBHandle();
if (!$dbh) {
    header('HTTP/1.0 500 Internal Server Error');
    exit(1);
}

$uid = getUIDFromSID($dbh);
if ($uid !== FALSE)
    echo json_encode(getUserData($dbh, $uid));
?>