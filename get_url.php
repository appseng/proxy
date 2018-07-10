<?php
if (isset($_GET['url'])) {
    $filename = $_GET['url'];
    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
    $contents = file_get_contents($filename);
    $ct = '';
    if (preg_match("~\.(jpeg|jpg|png)$~", $fileExt) == 1) {
        $ct ='image';
    } elseif (preg_match("~\.(js|css)$~", $fileExt) == 1) {
        $ct ='html';
    }
    header("Content-type: $ct/$fileExt");
    echo $contents;
}

