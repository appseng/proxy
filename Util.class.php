<?php

class Util {
    
    public $scheme = 'http:';
    public $host = '';
    public $path = '';

    public function replaceLink($URL, $scheme, $sHost, $sPath) {
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

    public function setSchemeHostPath($page) {
        $pu = parse_url($page);
        if (empty($pu['scheme'])) {
            $page = $scheme . '//' . $page;
            $pu = parse_url($page); 
        }
        $this->scheme = $pu['scheme'] . ':';
        $this->host =  $this->scheme . '//' . $pu['host'];
        $this->path = preg_replace("~^$this->host~", '', $page);
    }
}