<?php
/*************************************************
 *
 * Proxy - Web-pages simple proxy
 * Author: Dmitry Kuznetsov <appseng@yandex.ru>
 * Copyright (c): 2017, all rights reserved
 * Version: 1.0.1
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

    public function __construct($proxyPageParam = 'proxy_page', $proxyHostParam = 'proxy_host', $proxyResetParam = 'proxy_reset') {
        $this->params['proxyPageParam'] = $proxyPageParam;
        $this->params['proxyHostParam'] = $proxyHostParam;
        $this->params['proxyResetParam'] = $proxyResetParam;
    }

    public function show() {      
        session_start();
        
        if (isset($_GET[$this->params['proxyResetParam']]) || !$this->checkTimeout()) {
            $this->reset();
            return false;
        }
        
        $page = isset($_GET[$this->params['proxyPageParam']]) ? $_GET[$this->params['proxyPageParam']] : '';
        $host = isset($_SESSION[$this->params['proxyHostParam']]) ? $_SESSION[$this->params['proxyHostParam']] : '';
        
        if ($host !== '' || $page !== '') {
            $sHost = ($host !== '') ? $host : $page;

            $_SESSION[$this->params['proxyHostParam']] = $sHost;

            $sPage = ($host !== '' && $page !== '' && $host !== $page)? "/{$page}" : '';
        
            $params = "?";
            $i = 0;
            foreach($_GET as $key => $value) {
                if ($key === $this->params['proxyPageParam']) continue;
        
                if ($i == 0) {
                    $params .= "{$key}={$value}";
                    $i++;
                }
                else {
                    $params .= "&{$key}={$value}";
                }
            }
            
            $URL =  $sHost . $sPage . $params;
            $URL = str_replace("//", "/", $URL);
        
            if ("/".$sHost."/" == $sPage) {
                $URL = str_replace("{$sHost}/{$sHost}", $sHost, $URL);
            }
            
            $pageHTML = $this->fetchPage($URL);

            $pageHTML = preg_replace("/action=(\"|')?\/(\"|')?/", "action=\"" . $sHost . "\"", $pageHTML);
            $pageHTML = preg_replace("/href=(\"|')?\/\?/", "href=\"" . $sHost ."/?", $pageHTML);
         
            $this->echoPage($URL, $pageHTML);
        } else {
            $this->echoDefault();
        }
    }
    
    protected function fetchPage($URL) {
        $snoopy = new Snoopy;
        $snoopy->fetch("http://" . $URL);
        return $snoopy->results;
    }

    protected function echoPage($URL, $pageHTML) {
        echo 'You are currently on "' . $URL . '" <a href="index.php?' . $this->params['proxyResetParam'] . '=exit"><strong>Click</strong> to leave</a>';
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

    protected function reset() {
        session_unset();
        session_destroy();
        header('Location: .');
    }
}