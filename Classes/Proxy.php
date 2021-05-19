<?php

class Proxy {

    protected $params = [];
    private $snoopy;
    private $util;
    private $URL;
    private $pageHTML;

    public function __construct($proxyDirParam = '/proxy', $proxyPageParam = 'proxy_page', $proxyGetPHP = 'get.php', $proxyGetParam = 'u') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyDirParam'] = $proxyDirParam;
        $this->params['getPHP'] = $proxyGetPHP;
        $this->params['proxyGetParam'] = $proxyGetParam;
        $this->util = new Util($proxyPageParam);
    }

    public function show() {      
        $page = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';

        $validHostnameRegex = "^https?://([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])(\.([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9/]))*";
        
        if ($page !== '' && preg_match("~$validHostnameRegex~", $page) == 1) {

            $pageHeaders = @get_headers($page);
            if(!$pageHeaders || $pageHeaders[0] == 'HTTP/1.1 404 Not Found') {
                $this->echoDefault();
            }
            else {
                $this->URL = $this->util->getURL($page);
                //echo $this->URL;
                $this->fetchPage();

                $this->pageHTML = preg_replace("~target=(\"|')?_blank(\"|')?~", '', $this->pageHTML);
                
                $util = $this->util;
                //proxy img|script|link|input|meta
                $this->pageHTML = preg_replace_callback("~<(img|script|link|input|meta)(\s[^>]*?\s*)(href|src|content)=['\"]?([^>'\"\s]*)(\.)?(jpeg|jpg|gif|png|svg|css|js|ico)([^>'\"\s]*)['\"\s]?([^>]*)>~im", function($m) use($util) {
                    $src = $m[4].$m[5].$m[6].$m[7];
                    $src = $util->replaceLink($src);
                    return "<{$m[1]}{$m[2]}{$m[3]}=\"{$this->params['proxyDirParam']}/{$this->params['getPHP']}?{$this->params['proxyGetParam']}=$src\"{$m[8]}>";
                }, $this->pageHTML);

                //proxy style="... url() ..."
                $this->pageHTML = preg_replace_callback("~<([^>]*)style=['\"]?([^>'\"]*)url\s*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg|ico)([^>'\"\s\)]*)\)([^>\"\']*)['\"]?([^>]*)>~im", function($m) use($util) {
                    $src = $m[3].'.'.$m[4].$m[5];
                    $src = $util->replaceLink($src);                
                    return "<{$m[1]}style=\"{$m[2]}url({$this->params['proxyDirParam']}/{$this->params['getPHP']}?{$this->params['proxyGetParam']}=$src){$m[6]}\"{$m[7]}>";
                }, $this->pageHTML);

                //proxy <style>: { }...url() ...}"
                $this->pageHTML = preg_replace_callback("~<style([^>]*)?>(\s[^<>]*?\s*)?</style>~im", function($m) use($util) {
                    $style = empty($m[2]) ? '' : $m[2];
                    $add = empty($m[1]) ? '' : $m[1];
                    if (!empty($style)) {
                        $style = preg_replace_callback("~url(\s)*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg|ico)\)~im", function($mi) use($util) {
                            $src = $mi[2] . '.' . $mi[3];
                            $src = $util->replaceLink($src);
                            return "url(\"{$this->params['proxyDirParam']}/{$this->params['getPHP']}?{$this->params['proxyGetParam']}=$src\")";
                        }, $style);
                    }
                    return "<style$add>$style</style>";
                }, $this->pageHTML);

                //proxy anchors
                $this->pageHTML = preg_replace_callback("~<a(\s[^>]*?\s*)href=[\"']?([^>'\"\s]*)['\"\s]?([^>]*)>~i", function($m) use($util) {
                    $link = $m[2];
                    //ignore magnet links
                    if (preg_match('~^magnet:~', $link) == 1) {
                        return $m[0];
                    }
                    $link = $util->replaceLink($link);
                    $replacement = $this->params['proxyDirParam'] . '/index.php?' . $this->params['proxyPageParam'] . '=' . $link;
                    return "<a{$m[1]}href=\"$replacement\"{$m[3]}>";
                }, $this->pageHTML);
                $this->echoPage();
            }
        } else {
            $this->echoDefault();
        }
    }

    protected function fetchPage() {
        $this->snoopy = new Snoopy;
        $this->snoopy->fetch($this->URL);
        $this->pageHTML = $this->snoopy->getResults();
    }

    protected function echoPage() {
        echo $this->pageHTML;
    }

    protected function echoDefault() {
    ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <title>Simple web-proxy</title>
            <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="center">
                <form action="index.php" method="GET">
                    <label for="<?= $this->params['proxyPageParam'] ?>">Page: </label>
                    <input type="text" name="<?= $this->params['proxyPageParam'] ?>" value="https://" placeholder="https://">
                    <input type="submit" value=" Go ">
                </form>
            </div>        
        </body>
        </html>
    <?php
    }

    private function replaceLink() {
        return $this->util->replaceLink($this->URL);
    }
}