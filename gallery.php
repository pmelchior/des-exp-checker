<?php

include "common.php.inc";
$dbh = getDBHandle();

$stm = $dbh->prepare('SELECT files.expname, files.ccd, qa.qaid as qa_id, qa.detail, qa.timestamp, users.username FROM qa JOIN files ON (files.fileid=qa.fileid) JOIN users ON (qa.userid = users.userid) WHERE qa.qaid IN (SELECT MIN(qa.qaid) FROM qa WHERE problem=1000 GROUP BY qa.fileid)');
$stm->execute();
$response = $stm->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['output'])) {
    if ($_GET['output'] == "html") {
        echo "<table class='table table-condensed table-striped'><thead><tr><th>Image</th><th>Description</th><th>Username</th><th>Timestamp</th></tr></thead><tbody>\n";
        foreach ($response as $row) {
            echo "<tr><td class='imagenamecol'><a href='viewer.html?release=".$config['release']."&expname=".$row['expname']."&ccd=".$row['ccd']."&qa_id=".$row['qa_id']."'>".$row['expname'].", CCD ".$row['ccd']."</a></td>\n";
            echo "<td>".$row['detail']."</td>\n";
            echo "<td>".$row['username']."</td>\n";
            echo "<td class='timecol'>".$row['timestamp']."</td>\n";
        }
        echo "</tbody></table>";
    }
    if ($_GET['output'] == "json") {
        echo json_encode($response);
    }
}
else 
    echo json_encode($response);
?>