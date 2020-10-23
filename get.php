<?php
namespace Main;

mb_internal_encoding("UTF-8");
spl_autoload_register();

if (isset($_GET['u'])) {
    $validFileRegex = "^(https?://)?([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,350}[a-zA-Z0-9])(\.([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9/]))*";

    $filename = $_GET['u'];
    if (empty($filename) || preg_match("~$validFileRegex~", $filename) != 1)
        return;
        
    $filename = str_replace(' ', '+', $filename);

    $fileExt = pathinfo($filename, PATHINFO_EXTENSION);

    $ct = '';
    if (preg_match("~(jpeg|jpg|png|gif|svg|ico)~", $fileExt, $ext) == 1
    || preg_match("~(jpeg|jpg|png|gif|svg|ico)\?~", $filename, $ext) == 1) {
        $ct ='image';
        if ($ext[1] == 'svg') {
            $type = 'svg+xml';
        } elseif ($ext[1] == 'ico') {
            $type = 'vnd.microsoft.icon';
        } else {
            $type = $ext[1];
        }
        $contents = file_get_contents($filename);
        header("Content-type: $ct/$type");
    } elseif (preg_match("~(js|css)~", $fileExt, $ext) == 1
     || preg_match("~(js|css)\?~", $filename, $ext) == 1) {
        $ct ='text';
        $type = 'javascript';
        
        if($ext[1] === 'css') {
            $type = 'css';
        }
        
        $snoopy = new \Classes\Snoopy;
        $snoopy->fetch($filename);
        $contents = $snoopy->getResults();

        if ($type === 'css') {
            
            $settings = new \Classes\Settings;
            $util = new \Classes\Util($settings->proxyPageParam);

            $filename = $util->getURL($filename);

            $proxyGetPHP = $settings->proxyGetPHP;
            $contents = preg_replace_callback("~url\s*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg)~im", function($m) use($util, $proxyGetPHP) {
                $src = $m[1] . '.' . $m[2];                
                $src = $util->replaceLink($src);                
                return "url({$util->params['proxyPageParam']}/$proxyGetPHP?u=$src";
            }, $contents);
        }
        header("Content-type: $ct/$type");
    }
    echo $contents;
}