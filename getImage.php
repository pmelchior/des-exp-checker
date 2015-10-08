<?php

function download_file($file, $revalidate = False) { // $file = include path 
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        if ($revalidate) {
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
        } else {
                header('Date: Thu, 03 Jul 2014 00:00:00 GMT');
                header('Cache-Control: max-age=31556926');
        }
        ob_clean();
        flush();
        readfile($file);
        exit;
}
include("common.php.inc");
setRelease();

if (isset($_GET['type'])) {
   $dbh = getDBHandle();
   // depending on release, need runname or whole other sheband for paths
   if ($config['release'] == "SVA1" || $config['release'] == "Y1A1") {
      $sql = 'SELECT runname FROM runs WHERE expname = ?';
      $stmt = $dbh->prepare($sql);
      $stmt->bindParam(1, $_GET['expname'], PDO::PARAM_STR, 14);
      $stmt->execute();
      $res = check_or_abort($stmt);
      $row = $res->fetch(PDO::FETCH_ASSOC);

      if ($_GET['type'] == "fov") {
        $path = str_replace("%r", $row['runname'], $config['fovpath'][$config['release']]);
        $path = str_replace("%e", $_GET['expname'], $path);
	download_file($path);
      }

      if ($_GET['type'] == "dm") {
      	$path = str_replace("%r", $row['runname'], $config['dmpath'][$config['release']]);
        $path = str_replace("%e", $_GET['expname'], $path);
	$path = str_replace("%c", sprintf("%02d", $_GET['ccd']), $path);
	echo $path;
      }
   }
   else {
     if ($_GET['type'] == "dm")
       echo "not available yet!";
     if ($_GET['type'] == "fov")
       download_file("assets/fov_not_available.png");
   }
}
else {
        $path = $config['fitspath'][$config['release']];
        download_file($path.$_GET['name'], True);
}
?>
