<?php

include "common.php.inc";
$dbh = getDBHandle();

function getCountOfProblem($dbh, $problem, $uid=NULL) {
    global $config;
    $codes = getProblemCodes();
    if (in_array($problem, array_keys($codes))) {
        $code = $codes[$problem];
        $sql = 'SELECT problem, detail, COUNT(*) as count FROM qa WHERE release=? AND problem=?';
        if (isset($uid))
            $sql .= ' AND userid=?';
        if ($code == 255)
            $sql .= ' GROUP BY detail ORDER BY COUNT(*) DESC';
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(1, $config['release'], PDO::PARAM_STR, 4);
        $stmt->bindParam(2, $code, PDO::PARAM_INT);
        if (isset($uid))
            $stmt->bindParam(3, $uid, PDO::PARAM_INT);
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
