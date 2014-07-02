<?php 
function download_file($file) { // $file = include path 
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
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
        $path = $config['fitspath'][$config['release']];
        download_file($path.$_GET['name']);
}
?>
