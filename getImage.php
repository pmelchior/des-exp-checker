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
$path = array("SVA1"=> "se001grizt", "Y1A1"=>"se004grizY");
$path = str_replace('%s', $path[$config['release']], $config['fitspath']);
download_file($path.$_GET['name']);
?>
