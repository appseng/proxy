<?php

mb_internal_encoding("UTF-8");
spl_autoload_register(function($class) {
    require_once "Classes/$class.php";
});

$settings = new Settings;

$proxy = new Proxy($settings->proxyDirParam, $settings->proxyPageParam, $settings->proxyGetPHP);
$proxy->show();
