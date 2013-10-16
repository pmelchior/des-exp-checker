<?php

include "common.php.inc";
$dbh = getDBHandle();

$stm = $dbh->prepare("SELECT files.files.expname, files.files.ccd, qa.comment, qa.timestamp, users.username FROM qa JOIN files ON (files.files.rowid=qa.fileid) JOIN users ON (qa.userid = users.rowid) WHERE qa.problem=-1 ORDER BY qa.timestamp ASC");
$stm->execute();
$response = $stm->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['output'])) {
    if ($_GET['output'] == "html") {
        echo "<table class='table table-condensed table-striped'><thead><tr><th>Image</th><th>Description</th><th>Username</th><th>Timestamp</th></tr></thead><tbody>\n";
        foreach ($response as $row) {
            $row['comment'] = json_decode($row['comment']);
            echo "<tr><td><a href='viewer.html?expname=".$row['expname']."&ccd=".$row['ccd']."'>".$row['expname'].", CCD ".$row['ccd']."</a></td>\n";
            echo "<td>".$row['comment']->detail."</td>\n";
            echo "<td>".$row['username']."</td>\n";
            echo "<td>".$row['timestamp']."</td>\n";
        }
        echo "</tbody></table>";
    }
}
else 
    echo json_encode($response);
?>