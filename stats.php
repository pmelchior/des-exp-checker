<?php

include "common.php.inc";
$dbh = getDBHandle();

$stats = array();
// basic stats: how many total files, how many done
if (isset($_GET['total'])) {
    $stmt = $dbh->query('SELECT COUNT(1) from files.files');
    $stats['total'] = $stmt->fetchColumn();
    $stmt = $dbh->query('SELECT COUNT(DISTINCT(fileid)) from qa');
    $stats['done'] = $stmt->fetchColumn();
}

if (isset($_GET['breakup'])) {
    // how many have problems
    $stmt = $dbh->prepare('SELECT COUNT(DISTINCT(fileid)), COUNT(fileid) from qa WHERE problem = ?');
    $codes = getProblemCodes();
    $problems = array();
    foreach ($codes as $name => $code) {
        if ($code >= 0) {
            $stmt->bindParam(1, $code, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_NUM);
            if ($code == 0)
                $stats['fine'] = $row[0];
            else
                array_push($problems, array("name" => $name, "distinct" => $row[0], "all" => $row[1]));
        }
    }
    $stats['breakup'] = $problems;
}

if (isset($_GET['throughput'])) {
    // how many have problems
    $stmt = $dbh->query('SELECT substr(timestamp,0,11) as date, COUNT(1) as marks, COUNT(DISTINCT(fileid)) as files FROM qa GROUP BY substr(timestamp,0,11)');
    $throughput = array();
    while($row = $stmt->fetch(PDO::FETCH_ASSOC))
        array_push($throughput, $row);
    $stats['throughput'] = $throughput;
}

echo json_encode($stats);

?>