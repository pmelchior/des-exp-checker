<?php

include "common.php.inc";
$dbh = getDBHandle();

$stm = $dbh->prepare('SELECT files.expname, files.ccd, qa.qaid as qa_id, qa.detail, qa.timestamp, users.username FROM qa JOIN files ON (files.fileid=qa.fileid) JOIN users ON (qa.userid = users.userid) WHERE qa.qaid IN (SELECT MIN(qa.qaid) FROM qa WHERE problem=1000 AND detail IS NOT NULL GROUP BY qa.fileid)');
$stm->execute();
$response = $stm->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($response);
?>