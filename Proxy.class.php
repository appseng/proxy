<?php
/*************************************************
 *
 * SimpleProxy - a simple web-proxy
 * Author: Dmitry Kuznetsov <appseng@yandex.ru>
 * Copyright (c): 2017-2020, all rights reserved
 * Version: 0.3.3
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

    public function __construct($proxyDirParam = '/proxy', $proxyPageParam = 'proxy_page') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyDirParam'] = $proxyDirParam;
    }

    public function show() {      
        $page = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';

        $validHostnameRegex = "^https?://([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])(\.([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]{0,61}[a-zA-Z0-9/]))*";
        if ($page !== '' && preg_match("~$validHostnameRegex~", $page) == 1) {
            $sPage = '';
            $sHost = '';
            $sPath = '';
            $scheme = 'http:';
            $pu = parse_url($page);
            if (empty($pu['scheme'])) {
                $page = $scheme . '//' . $page;
                $pu = parse_url($page); 
            }
            $scheme = $pu['scheme'] . ':';
            $sHost = $scheme . '//' . $pu['host'];
            $sPath = preg_replace("~^$sHost~", '', $page);//url - scheme - host
            preg_match('~(/[^?&]*)[?]?.*~', $sPath, $m);

            if (count($m) > 0) {// only file
                $pageURL = substr($page, 0, strrpos($page,'/')+1);
            }
            $sPath = preg_replace('~[?].*$~', '', $sPath);
            $params = "?";
            foreach ($_GET as $key => $value) {
                if ($key === $this->params['proxyPageParam']) {
                    if (strrpos($value, '?') > 0) {
                        $param = explode('?', $value)[1];
                        if (strrpos($param, '=') > 0) {
                            $pKV = explode('=', $param);
                            $params .= "{$pKV[0]}={$pKV[1]}";
                        }
                    }
                } else {
                    $params .= "&{$key}={$value}";
                }
            }
            $params = (strlen($params) == 1) ? '' : $params;
            $URL =  $sHost . $sPath . $params;
            $pageHTML = $this->fetchPage($URL);
            $pageHTML = preg_replace("~target=(\"|')?_blank(\"|')?~", '', $pageHTML);

            //proxy img|script|link|input|meta
            $pageHTML = preg_replace_callback("~<(img|script|link|input|meta)(\s[^>]*?\s*)(href|src|content)=['\"]?([^>'\"\s]*)(\.)?(jpeg|jpg|gif|png|svg|css|js|ico)([^>'\"\s]*)['\"\s]?([^>]*)>~im", function($m) use($sHost, $scheme, $sPath) {
                $src = $m[4].$m[5].$m[6].$m[7];
                $src = $this->replaceLink($src, $scheme, $sHost, $sPath);
                return "<{$m[1]}{$m[2]}{$m[3]}=\"{$this->params['proxyDirParam']}/get.php?u=$src\"{$m[8]}>";
            }, $pageHTML);

            //proxy style="... url() ..."
            $pageHTML = preg_replace_callback("~<([^>]*)style=['\"]?([^>'\"]*)url\s*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg|ico)([^>'\"\s\)]*)\)([^>\"\']*)['\"]?([^>]*)>~im", function($m) use($sHost, $scheme, $sPath) {
                $src = $m[3].'.'.$m[4].$m[5];
                $src = $this->replaceLink($src, $scheme, $sHost, $sPath);                
                return "<{$m[1]}style=\"{$m[2]}url({$this->params['proxyDirParam']}/get.php?u=$src){$m[6]}\"{$m[7]}>";
            }, $pageHTML);

            //proxy anchors
            $pageHTML = preg_replace_callback("~<a(\s[^>]*?\s*)href=[\"']?([^>'\"\s]*)['\"\s]?([^>]*)>~i", function($m) use($sHost, $scheme, $sPath) {
                $link = $m[2];
                //ignore magnet links
                if (preg_match('~^magnet:~', $link) == 1) {
                    return $m[0];
                }
                $link = $this->replaceLink($link, $scheme, $sHost, $sPath);
                $replacement = $this->params['proxyDirParam'] . '/index.php?' . $this->params['proxyPageParam'] . '=' . $link;
                return "<a{$m[1]}href=\"$replacement\"{$m[3]}>";
            }, $pageHTML);
            $this->echoPage($pageHTML);
        } else {
            $this->echoDefault();
        }
    }

    protected function fetchPage($URL) {
        $this->snoopy = new Snoopy;
        $this->snoopy->fetch($URL);
        return $this->snoopy->getResults();
    }

    protected function echoPage($pageHTML) {
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
            <link rel="stylesheet" href="style.css">
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

    private function replaceLink($URL, $scheme, $sHost, $sPath) {
        //absolute url
        if (preg_match('~^(https?:)?(//?.*)~', $URL, $m0) == 1) {
            $lu = parse_url($URL);
            if (!isset($lu['host'])) {
                $urlHost = (empty($m0[1]))? $sHost : '';
                $URL = $urlHost . $URL;
            } elseif (!isset($lu['scheme'])) {
                $URL = $scheme . $URL;
            } 
        }//relative url    
        elseif (preg_match('~^(\.\.?/)?(.*#)?.*~', $URL, $m1) == 1) {
            $URL = $sHost. $sPath . $URL;
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