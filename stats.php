<?php

include "common.php.inc";
$dbh = getDBHandle();

$stats = array();
// basic stats: how many files done
if (isset($_GET['total'])) {
    // to the degree that submission come in slower than 1/second, this is correct
    $stmt = $dbh->query('SELECT COUNT(DISTINCT(timestamp)) FROM qa');
    $stats['total'] = intval($stmt->fetchColumn());
}
if (isset($_GET['today'])) {
    $date = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = $dbh->query("SELECT COUNT(DISTINCT(timestamp)) FROM qa WHERE timestamp > '".$date."%'");
    $stats['today'] = intval($stmt->fetchColumn());
}
if (isset($_GET['breakup'])) {
    // how many have problems
    $stmt = $dbh->prepare('SELECT COUNT(DISTINCT(fileid)) from qa WHERE problem = ?');
    $problems = array();
    $stats['checked'] = 0;
    foreach ($config['problem_code'] as $name => $code) {
        if ($code >= 0 && $code < 1000) {
            $stmt->bindParam(1, $code, PDO::PARAM_INT);
            $stmt->execute();
            if ($code == 0) {
                if($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $stats['fine'] = intval($row[0]);
                    $stats['checked'] += intval($row[0]);
                }
            }
            else {
                $this_problem = array("name" => $name, "all" => 0, "false_positive" => 0);
                if($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $stats['checked'] += intval($row[0]);
                    $this_problem['all'] += intval($row[0]);
                }
                $code *= -1;
                $stmt->bindParam(1, $code, PDO::PARAM_INT);
                $stmt->execute();
                if($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $stats['checked'] += intval($row[0]);
                    $this_problem['all'] += intval($row[0]);
                    $this_problem['false_positive'] += intval($row[0]);
                }
                array_push($problems, $this_problem);
            }
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
