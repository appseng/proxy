<?php
/*************************************************
 *
 * SimpleProxy - a simple web-proxy
 * Author: Dmitry Kuznetsov <appseng@yandex.ru>
 * Copyright (c): 2017-2019, all rights reserved
 * Version: 0.2.2
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

    public function __construct($proxyDirParam = '/proxy', $proxyPageParam = 'proxy_page', $proxyResetParam = 'proxy_reset') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyResetParam'] = $proxyResetParam;
        $this->params['proxyDirParam'] = $proxyDirParam;
    }

    public function show() {      
        if (isset($_GET[$this->params['proxyResetParam']])) {
            $this->reset();
            return false;
        }
        $page = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';

        $validHostnameRegex = "^https?://([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])\.([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9/])*";
        if ($page !== '' && preg_match("~$validHostnameRegex~", $page) == 1) {
            $sPage = '';
            $sHost = '';
            $sPath = '';
            $sDir = '';
            $scheme = 'http:';
            $pu = parse_url($page);
            if (empty($pu['scheme'])) {
                $page = $scheme . '//' . $page;
                $pu = parse_url($page); 
            }
            $scheme = $pu['scheme'] . ':';
            $sHost = $scheme . '//' . $pu['host'];
            $sPath = preg_replace('~^' . $sHost . '~', '', $page);//url - scheme - host
            preg_match('~(/[^?&]*)[?]?.*~', $sPath, $m);

            if (count($m) > 0) {// only file
                $pageURL = substr($page, 0, strrpos($page,'/')+1);
            }
            else {// only last directory
                preg_match('~/[^/]*(/?)$~', $page, $m);
                $sPage = count($m) > 0? $m[0] : '';
                $sDir =  $page . $m[1];//current directory
            }
            $sPath = preg_replace('~[?].*$~', '', $sPath);
            $params = "?";
            foreach ($_GET as $key => $value) {
                if ($key === $this->params['proxyPageParam']) {
                    if (strrpos($value, '?') > 0) {
                        $param = explode('?', $value)[1];
                        $pKV = explode('=', $param);
                        $params .= "{$pKV[0]}={$pKV[1]}";
                    }
                } else {
                    $params .= "&{$key}={$value}";
                }
            }
            $params = (strlen($params) == 1) ? '' : $params;
            $URL =  $sHost . $sPath . $params;
            $pageHTML = $this->fetchPage($URL);
            $pageHTML = preg_replace("~target=(\"|')?_blank(\"|')?~", '', $pageHTML);

            //proxy img|script|link|input
            $pageHTML = preg_replace_callback("~<(img|script|link|input|meta)(\s[^>]*?\s*)(href|src|content)=['\"]?([^>'\"\s]*)(\.)?(jpeg|jpg|gif|png|svg|css|js|ico)([^>'\"\s]*)['\"\s]?([^>]*)>~im", function($m) use($sDir, $sHost, $scheme) {
                $src = $m[4].$m[5].$m[6].$m[7];
                $src = $this->linkReplace($src, $scheme, $sHost, $sDir);
                return "<{$m[1]}{$m[2]}{$m[3]}=\"{$this->params['proxyDirParam']}/get.php?u=$src\"{$m[8]}>";
            }, $pageHTML);

            //proxy style="... url() ..."
            $pageHTML = preg_replace_callback("~<([^>]*)style=['\"]?([^>'\"]*)url\s*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg|ico)([^>'\"\s\)]*)\)([^>\"\']*)['\"]?([^>]*)>~im", function($m) use($sDir, $sHost, $scheme) {
                $src = $m[3].'.'.$m[4].$m[5];
                $src = $this->linkReplace($src, $scheme, $sHost, $sDir);                
                return "<{$m[1]}style=\"{$m[2]}url({$this->params['proxyDirParam']}/get.php?u=$src){$m[6]}\"{$m[7]}>";
            }, $pageHTML);

            //proxy anchors
            $pageHTML = preg_replace_callback("~<a(\s[^>]*?\s*)href=[\"']?([^>'\"\s]*)['\"\s]?([^>]*)>~i", function($m) use($sHost, $sDir, $scheme) {
                $link = $m[2];
                //ignore magnet links
                if (preg_match('~^magnet:~', $link) == 1) {
                    return $m[0];
                }
                $link = $this->linkReplace($link, $scheme, $sHost, $sDir);
                $replacement = $this->params['proxyDirParam'] . '/index.php?' . $this->params['proxyPageParam'] . '=' . $link;
                return "<a{$m[1]}href=\"$replacement\"{$m[3]}>";
            }, $pageHTML);
            $this->echoPage($URL, $pageHTML);
        } else {
            $this->echoDefault();
        }
    }

    protected function fetchPage($URL) {
        $this->snoopy = new Snoopy;
        $this->snoopy->fetch($URL);
        return $this->snoopy->getResults();
    }

    protected function echoPage($URL, $pageHTML) {
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
            <title>Simple web-proxy</title>
            <style>
                * { 
                    margin: 0;
                    padding: 0;                    
                }
                body, html {
                    height: 100%;
                }
                body {
                    background-color: #333;
                }
                .center {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100%;
                }
                label {
                    color: #5b6dcd;
                    font-size: 48px;
                }
                input {
                    font-size: 36px;
                    color: lightblue;
                    background-color: #5b6dcd;
                }
                input[type="submit"] {
                    border-radius: 50% 20% / 10% 40%;
                    border: #5b6dcd solid 15px;             
                }
                input[type="text"] {
                    border: #5b6dcd solid 15px;                                       
                    border-radius: 15px;
                }
                form {
                    border-left: #5b6dcd solid 15px;
                    padding: 10px;
                    background-color: #ddd;
                }
            </style>
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

    protected function reset() {
        header('Location: .');
    }

    private function linkReplace($URL, $scheme, $sHost, $sDir) {
        //absolute url
        if (preg_match('~^(https?:)?(//?.*)~', $URL, $m0) == 1) {
            //$link = $sHost. $m0[2];
            $lu = parse_url($URL);
            if (!isset($lu['host'])) {
                $urlHost = (empty($m0[1]))? $sHost : '';
                $URL = $urlHost.$URL;
            } elseif (!isset($lu['scheme'])) {
                $URL = $scheme . $URL;
            }  
        }//relative url
        elseif (preg_match('~^(\.\./)?.*$~', $URL, $m1) == 1) {
            $URL = $sDir . $m1[0];
        }
        
        // remote parent directory link '..'
        $URL = preg_replace('~//?\.?\.\./~','/../', $URL);
        while (preg_match('~(/[^/]+/\.\./)~', $URL, $murl) == 1) {
            $URL = str_replace($murl[1],'/', $URL);
        }
        $URL = str_replace('/./','/', $URL);

        return $URL;
    }
}