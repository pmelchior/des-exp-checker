<?php

include "common.php.inc";
$dbh = getDBHandle();

function getCountOfProblem($dbh, $problem, $uid=NULL) {
    global $config;
    if (in_array($problem, array_keys($config['problem_code']))) {
        $code = $config['problem_code'][$problem];
        $sql = 'SELECT problem, detail, COUNT(DISTINCT(fileid)) as count FROM qa WHERE problem=?';
        if (isset($uid))
            $sql .= ' AND userid=?';
        if ($code == 255)
            $sql .= ' AND detail IS NOT NULL GROUP BY detail ORDER BY `count` DESC, detail';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $code, PDO::PARAM_INT);
        if (isset($uid))
            $stmt->bindParam(2, $uid, PDO::PARAM_INT);
        $stmt->execute();
        $res = check_or_abort($stmt);
        $problems = array();
        while($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $row['problem'] = $problem;
            array_push($problems, $row);
        }
        return $problems;
    } else
        return FALSE;
}

if (isset($_GET['fileid'])) {
    $problems = getProblems($dbh, $_GET['fileid']);
}
if (isset($_GET['problem'])) {
    $uid = NULL;
    if (isset($_GET['my_problems']))
        $uid = getUIDFromSID($dbh);
    $problems = getCountOfProblem($dbh, $_GET['problem'], $uid);
}
echo json_encode($problems);

?>
