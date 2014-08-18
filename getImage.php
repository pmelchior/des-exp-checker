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

if ($_GET['type'] == "fov") {
        $path = str_replace("%r", $_GET['runname'], $config['fovpath'][$config['release']]);
        $path = str_replace("%e", $_GET['expname'], $path);
        download_file($path);
}
else {
        $path = $config['fitspath'];
        download_file($path.$_GET['name'], True);
}
?>
