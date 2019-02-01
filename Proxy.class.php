<?php
/*************************************************
 *
 * SimpleProxy - Web-pages simple proxy
 * Author: Dmitry Kuznetsov <appseng@yandex.ru>
 * Copyright (c): 2017-2018, all rights reserved
 * Version: 1.1.2
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

    public function __construct($proxyPageParam = 'proxy_page', $proxyResetParam = 'proxy_reset') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyResetParam'] = $proxyResetParam;
    }

    public function show() {      
        if (isset($_GET[$this->params['proxyResetParam']])) {
            $this->reset();
            return false;
        }
        $pageParam = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';
        if ($pageParam !== '') {
            $pageURL = '';
            $pu = parse_url($pageParam);
            $puHost = $pu['scheme'] . '://' . $pu['host'];
            $puPath = preg_replace('~^' . $puHost . '~', '', $pageParam);//url - scheme - host
            preg_match('~(/[^?&]*)[?]?.*~', $puPath, $m);
            if (count($m) > 0) {// only file
                $pageURL = substr($pageParam, 0, strrpos($pageParam,'/')+1);
            }
            else {// only last directory
                preg_match('~/[^/]*/?$~', $pageParam, $m);
                $pageURL =  $pageParam;//current directory
            }
            $puPath = preg_replace('~[?].*$~', '', $puPath);
            $pageParams = "?";
            foreach ($_GET as $key => $value) {
                if ($key === $this->params['proxyPageParam']) {
                    if (strrpos($value, '?') > 0) {
                        $param = explode('?', $value)[1];
                        $pKV = explode('=', $param);
                        $pageParams .= "{$pKV[0]}={$pKV[1]}";
                    }
                } else {
                    $pageParams .= "&{$key}={$value}";
                }
            }
            $pageParams = (strlen($pageParams) == 1) ? '' : $pageParams;
            $URL =  $puHost . $puPath . $pageParams;
            $pageHTML = $this->fetchPage($URL);
            //$pageHTML = preg_replace("/action=(\"|')?\/(\"|')?/", "action=\"" . $sHost . "\"", $pageHTML);
            $pageHTML = preg_replace("~target=(\"|')?_blank(\"|')?~", '', $pageHTML);
            //proxy img|script|link|input
            $pageHTML = preg_replace_callback("~<(img|script|link|input|meta)(\s[^>]*?\s*)(href|src|content)=['\"]?([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg|css|js)([^>'\"\s]*)['\"\s]?([^>]*)>~im", function($m) use($pageURL, $puHost, $pu) {
                $src = $m[4].'.'.$m[5].$m[6];
                //absolute url
                if (preg_match('~^(https?:/)?(/.*)~', $src, $m0) == 1) {
                    $su = parse_url($src);
                    if (!isset($su['host'])) {
                        $urlHost = (empty($m0[1]))? $puHost : '';
                        $src = $urlHost.$src;
                    } elseif (!isset($su['scheme'])) {
                        $src = $pu['scheme']. ':' . $src;
                    }          
                } else {//relative URL
                    $src = $pageURL.$src;
                    $src = str_replace('/.../','/../', $src);
                    $src = $this->parentLinkReplace($src);
                }
                
                return "<{$m[1]}{$m[2]}{$m[3]}=\"/proxy/get_url.php?url=$src\"{$m[7]}>";
            }, $pageHTML);
            //proxy style="... url() ..."
            $pageHTML = preg_replace_callback("~<([^>]*)style=['\"]?([^>'\"]*)url\s*\(([^>'\"\s]*)\.(jpeg|jpg|gif|png|svg)([^>'\"\s\)]*)\)([^>\"\']*)['\"]?([^>]*)>~im", function($m) use($pageURL, $puHost, $pu) {
                $src = $m[3].'.'.$m[4].$m[5];
                //absolute url
                if (preg_match('~^(https?:/)?(/.*)~', $src, $m0) == 1) {
                    $su = parse_url($src);
                    if (!isset($su['host'])) {
                        $urlHost = (empty($m0[1]))? $puHost : '';
                        $src = $urlHost.$src;
                    } elseif (!isset($su['scheme'])) {
                        $src = $pu['scheme']. ':' . $src;
                    }          
                } else {//relative URL
                    $src = $pageURL.$src;
                    $src = str_replace('/.../','/../', $src);
                    $src = $this->parentLinkReplace($src);
                }
                
                return "<{$m[1]}style=\"{$m[2]}url(/proxy/get_url.php?url=$src){$m[6]}\"{$m[7]}>";
            }, $pageHTML);

            //proxy anchors
            $pageHTML = preg_replace_callback("~<a(\s[^>]*?\s*)href=[\"']?([^>'\"\s]*)['\"\s]?([^>]*)>~i", function($m) use($puHost, $pageURL, $pu) {
                $link = $m[2];
                //ignore magnet links
                if (preg_match('~^magnet:~', $link) == 1) {
                    return $m[0];
                }
                //absolute url
                if (preg_match('~^(https?:/)?(/.*)~', $link, $m0) == 1) {
                    //$link = $sHost. $m0[2];
                    $lu = parse_url($link);
                    if (!isset($lu['host'])) {
                        $urlHost = (empty($m0[1]))? $puHost : '';
                        $link = $urlHost.$link;
                    } elseif (!isset($lu['scheme'])) {
                        $link = $pu['scheme']. ':' . $link;
                    }  
                }
                //relative url
                if (preg_match('~^(\.\./)?([^/]*)(/\.|/)?$~', $link, $m1) == 1) {
                    $link = $pageURL . $m1[0];
                    $link = str_replace('/.../','/../', $link);
                    $link = $this->parentLinkReplace($link);
                }
                $replacement = '/proxy/index.php?' . $this->params['proxyPageParam'] . '=' . $link;
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
                    <label for="<?= $this->params['proxyPageParam'] ?>">Page: </label>
                    <input type="text" name="<?= $this->params['proxyPageParam'] ?>" value="https://" placeholder="https://">
                    <input type="submit" value="Go">
                </form>
            </div>        
        </body>
        </html>
    <?php
    }

    protected function reset() {
        header('Location: .');
    }

    private function parentLinkReplace($URL) {
        while (preg_match('~(/[^/]+/\.\./)~', $URL, $m) == 1) {
            $URL = str_replace($m[1],'/', $URL);
        }    
        return $URL;
    }
}