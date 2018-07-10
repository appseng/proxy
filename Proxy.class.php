<?php
/*************************************************
 *
 * Proxy - Web-pages simple proxy
 * Author: Dmitry Kuznetsov <appseng@yandex.ru>
 * Copyright (c): 2017, all rights reserved
 * Version: 1.1.0
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *************************************************/
class Proxy {

    protected $params = [];
    private $snoopy;

    public function __construct($proxyPageParam = 'proxy_page', $proxyHostParam = 'proxy_host', $proxyResetParam = 'proxy_reset', $proxyURLParam = 'proxy_url', $proxyPathParam = 'proxy_path') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyHostParam'] = $proxyHostParam;
        $this->params['proxyPathParam'] = $proxyPathParam;
        $this->params['proxyResetParam'] = $proxyResetParam;
        $this->params['proxyURLParam'] = $proxyURLParam;
    }

    public function show() {      
        session_start();
        
        if (isset($_GET[$this->params['proxyResetParam']]) || !$this->checkTimeout()) {
            $this->reset();
            return false;
        }
        $page = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';
        $host = isset($_SESSION[$this->params['proxyHostParam']]) ? $_SESSION[$this->params['proxyHostParam']] : '';
        $path = isset($_SESSION[$this->params['proxyPathParam']]) ? $_SESSION[$this->params['proxyPathParam']] : '';
        
        if ($host !== '' || $page !== '') {
            $sPage = '';
            $sHost = '';
            $sPath = '';
            $sDir = '';
            $isOutURL = strpos($page, "http://") !== false || strpos($page, "https://") !== false;
            $pu = parse_url($page);
            if ($isOutURL) { //user defines url of a site to proxy it
                $sHost = $pu['scheme'] . '://' . $pu['host'];
            } 
            else {
                $sHost =  $host;                    
            }
            $sPath = preg_replace('~^' . $sHost . '~', '', $page);//url - scheme - host
            preg_match('~/[^/]*[?]?.*~', $page, $m);
            if (count($m) > 0) {// only file
                $sPage = $m[0];
                $sDir = preg_replace('~/[^/]*\.(html|htm|php)[?]?.*~', '/', $page);//current directory
            }
            else {// only last directory
                preg_match('~/[^/]*/?$~', $page, $m);
                $sPage = count($m) > 0? $m[0] : '';
                $sDir =  $page;//current directory
            }
            /*
            echo '$page='.$page;
            echo '$sHost='.$sHost;
            echo '$sPath='.$sPath;
            echo '$sPage='.$sPage;
            echo '$sDir='.$sDir;
            $_SESSION[$this->params['proxyHostParam']] = $sHost;
            $_SESSION[$this->params['proxyHostParam']] = $sPath;
            */
            $params = "?";
            $i = 0;
            foreach ($_GET as $key => $value) {
                if ($key === $this->params['proxyPageParam']) continue;
        
                if ($i == 0) {
                    $params .= "{$key}={$value}";
                    $i++;
                }
                else {
                    $params .= "&{$key}={$value}";
                }
            }            
            $urlPage = '';
            /*if (isset($_GET[$this->params['proxyURLParam']])) {
                $urlPage = $_SESSION["{$_GET[$this->params['proxyURLParam']]}"];
                $isOutURL = strpos($urlPage, "http://") !== false || strpos($urlPage, "https://") !== false;
                if ($isOutURL) {
                    $this->reset(false);
                    header('Location: /proxy/index.php?' . $this->params['proxyPageParam'] . '=' . $urlPage );
                    return true;
                }
                $sPath = $urlPage;
            }*/
            $params = (strlen($params) == 1) ? '' : $params;
            $URL =  $sHost . $sPath . $params;

            $pageHTML = $this->fetchPage($URL);

            $pageHTML = preg_replace("/action=(\"|')?\/(\"|')?/", "action=\"" . $sHost . "\"", $pageHTML);
            
            $pageHTML = preg_replace("~target=(\"|')?_blank(\"|')?~", '', $pageHTML);

            $pageHTML = preg_replace_callback("~<(img|script|link)[^>]*(href|src)=(['\"]?)(.*)\.(jpeg|jpg|png|css|js)['|\"]?[^>/]*([/]?>)~im", function($m) use($sDir,$sHost,$sPage) {
                //print_r($m);
                if (preg_match("~^http~i", $m[4]) === 1)
                    return "{$m[0]}";

                $URL1 = '';
                $rel = '';
                $type = '';

                if (strpos($m[0],'type="text/css"') > 0) {
                    $type = 'type="text/css"';
                }
                if (strpos($m[0],'rel="stylesheet"') > 0) {
                    $rel = 'rel="stylesheet"';
                }
                if (strpos($m[4], '/') == 0) {
                    $URL1 = "$sHost{$m[4]}.{$m[5]}";
                }
                elseif (strpos($m[4], '..') == 0) {
                    $URL1 = "$sDir{$m[4]}.{$m[5]}";
                    $URL1 = $this->parentLinkReplace($URL1);
                }
                else {//(strpos($sPage, '/') == 0) {
                    $parse = parse_url($sHost);
                    $host = $parse['host'];
                    $host = (strrpos($host, '/') == strlen($host)-1) ? substr($host,0,strlen($host)-2) : $host;
                    $URL1 =  $parse['scheme'] .'://'. $host . $sPage;
                }
                return "<{$m[1]} $rel $type {$m[2]}=\"/proxy/get_url.php?url=$URL1\"{$m[6]}";
            }, $pageHTML);
            $pageHTML = preg_replace_callback("~<a([^>]*)href=['\"]?([^>'\"\b]*)['\"\b]?([^>]*)>~im", function($m)use($sHost,$sPage,$sDir) {
                $linkEx = $m[2];
                //absolute url
                if (preg_match('~^(htt[p]?:/)?(/.*)~', $linkEx, $m0) == 1) {
                    $linkEx = $sHost. $m0[2];
                }
                //relative url
                if (preg_match('~^(\.\./)?([^/]*)(/\.|/)?$~', $linkEx, $m1) == 1) {
                    $linkEx = (count($m1) > 0) ? $sDir . $m1[0] : '';

                    $linkEx = str_replace('/.../','/../', $linkEx);
                }
                $linkEx = $this->parentLinkReplace($linkEx);

                $replacement = '/proxy/index.php?' . $this->params['proxyPageParam'] . '=' . $linkEx;
                return "<a {$m[1]} href=\"$replacement\" {$m[3]}>";
            }, $pageHTML);

            $this->echoPage($URL, $pageHTML);
        } else {
            $this->echoDefault();
        }
    }
    private function parentLinkReplace($URL) {
        while (preg_match('~(/[^/]+/\.\./)~', $URL, $murl) == 1)
        {
            $URL = str_replace($murl[1],'/', $URL);
        }
        return $URL;
    }
    protected function fetchPage($URL) {
        $this->snoopy = new Snoopy;
        $this->snoopy->fetch($URL);
        return $this->snoopy->getResults();
    }

    protected function echoPage($URL, $pageHTML) {
        echo 'You are currently on "' . $URL . '" <a href="/proxy/index.php?' . $this->params['proxyResetParam'] . '=exit"><strong>Click</strong> to leave</a>';
        echo $pageHTML;
    }

    protected function echoDefault() {
    ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="ie=edge">
            <title>Web-pages simple proxy</title>
        </head>
        <body>
            <div style="margin: 10px auto; width: 230px;">
                <form action="index.php" method="GET">
                    <label for="<?= $this->params['proxyPageParam'] ?>">Page URL:</label>
                    <input type="text" name="<?= $this->params['proxyPageParam'] ?>">
                </form>
            </div>        
        </body>
        </html>
    <?php
    }

    protected function checkTimeout() {
        if (!isset($_SESSION['timeout'])) {
            $_SESSION['timeout'] = time();
            return true;
        }
        if ($_SESSION['timeout'] + 10 * 60 < time()) {
            return false;
        } 
        return true;
    }

    protected function reset($redirect = true) {
        session_unset();
        session_destroy();
        if ($redirect)
            header('Location: .');
    }
}