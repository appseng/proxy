<?php

mb_internal_encoding("UTF-8");

if (isset($_GET['u'])) {
    $filename = $_GET['u'];
    if (empty($filename))
        return;
        
    $filename = str_replace(' ', '+', $filename);

    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);

    $ct = '';
    if (preg_match("~(\.)?(jpeg|jpg|png|gif|svg|ico)~", $fileExt, $ext) == 1) {
        $ct ='image';
        if ($ext[2] == 'svg') {
            $type = 'svg+xml';
        } elseif ($ext[2] == 'ico') {
            $type = 'vnd.microsoft.icon';
        } else {
            $type = $fileExt;
        }
        $contents = file_get_contents($filename);
        header("Content-type: $ct/$type");
    } elseif (preg_match("~(\.)?(js|css)~", $fileExt, $ext) == 1
     || preg_match("~(\.)?(js|css)\?~", $filename, $ext) == 1) {
        $ct ='text';
        $type = 'javascript';
        
        if($ext[2] === 'css') {
            $type = 'css';
        }
        require_once "Snoopy.class.php";
        $snoopy = new Snoopy;
        $snoopy->fetch($filename);
        $contents = $snoopy->getResults();
        header("Content-type: $ct/$type");
    } else {
        $contents = file_get_contents($filename);
    }
    echo $contents;
}