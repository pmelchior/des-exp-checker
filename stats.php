<?php

include "common.php.inc";
$dbh = getDBHandle();

// basic stats: how many total files, how many done
$stats = array();
$stmt = $dbh->query('SELECT COUNT(*) from files.files');
$stats['total'] = $stmt->fetchColumn();
$stmt = $dbh->query('SELECT COUNT(DISTINCT(fileid)) from qa');
$stats['done'] = $stmt->fetchColumn();

// how many have problems
$stmt = $dbh->prepare('SELECT COUNT(DISTINCT(fileid)), COUNT(fileid) from qa WHERE problem = ?');
$codes = getProblemCodes();
$problems = array();
foreach ($codes as $name => $code) {
    $stmt->bindParam(1, $code, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($code == 0)
        $stats['fine'] = $row[0];
    else
        array_push($problems, array("name" => $name, "distinct" => $row[0], "all" => $row[1]));
}
$stats['problems'] = $problems;
echo json_encode($stats);

?>