<?php

if (isset($_GET['expname'])) {
    include "common.php.inc";
    $dbh = new PDO('sqlite:exclusive/eyeball_squad.db');
    $dbh = check_or_abort($dbh);
    $sql = "SELECT wrong, comment FROM reports WHERE expnum = ?";
    $stmt = $dbh->prepare($sql);
    $expnum =  substr($_GET['expname'],8);
    $stmt->bindParam(1, $expnum, PDO::PARAM_INT);
    $stmt->execute();
    $res = check_or_abort($stmt);
    $row = $res->fetch(PDO::FETCH_ASSOC);
    echo json_encode($row); // yields false if not available
}
?>