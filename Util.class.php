<?php

class Util {
    
    public $scheme = 'http';
    public $path = '';
    public $host = '';
    public $URL;
    public $params = [];

    public function __construct($param) {
        $this->params['proxyPageParam'] = $param;
    }

    public function replaceLink($url) {
        $lu = parse_url($url);

        $scheme  = empty($lu['scheme'])? $this->scheme : $lu['scheme'];
        $host = empty($lu['host']) ? $this->host : $lu['host']; 
        $path = empty($lu['path']) ? $this->path : $lu['path']; 
        $query = empty($lu['query']) ?  '' : '?' . $lu['query'];
        $fragment = empty($lu['fragment']) ?  '' : '#' . $lu['fragment'];

        $link = $scheme;
        $link .= '://' . $host;
        //absolute url
        if (preg_match('~^(https?:)?(//?[^/]*)(.*)~', $url, $m) == 1) {
            $link .=  $m[3];
        }//relative url
        else {
            $pi = pathinfo($this->path);
            if (empty($pi['path']) && $url != '.'){
                $link .= '/';
            }
            if (empty($pi['basename']) || !empty($fragment) || $url == '.'){
                $link .= $this->path;
            }

            if ($url != '.') {
                $link .= $url;
            }
        }        
        return $link;
    }

    public function getURL($page) {
        $pu = parse_url($page);
        if (empty($pu['scheme'])) {
            $page = $this->scheme . '://' . $page;
        } else {
            $this->scheme = $pu['scheme'];
        }
        $this->host = $pu['host'];
        $this->path = preg_replace("~^$this->scheme://$this->host~", '', $page);
        $this->path = preg_replace('~(/\.)~', '/', $this->path);
        $this->path = preg_replace('~\?.*$~', '', $this->path);
        $params = "?";
        foreach ($_GET as $key => $value) {
            if ($key === $this->params['proxyPageParam']) {
                if (strrpos($value, '?') > 0) {
                    $param = explode('?', $value);
                    if(count($param) > 0) {
                        $param = $param[1];
                        if (strpos($param, '=') > 0) {
                            $pKV = explode('=', $param);
                            $params .= "{$pKV[0]}={$pKV[1]}";
                        }
                    }
                }
            } else {
                $params .= "&$key=$value";
            }
        }
        $params = (strlen($params) == 1) ? '' : $params;

        $this->URL =  $this->scheme . '://' . $this->host . $this->path . $params;
        return $this->URL;
    }
}