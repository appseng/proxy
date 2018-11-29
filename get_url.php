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

if (isset($_GET['url'])) {
    $filename = $_GET['url'];
    if (empty($filename))
        return;
        
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    $content = file_get_contents($filename);
    $contentType = '';
    if (preg_match("~\.(jpeg|jpg|png|gif|svg)~", $fileExtension) == 1) {
        $contentType ='image';
        if ($fileExtension == 'svg') {
            $fileExtension = 'svg+xml';
        }
    } elseif (preg_match("~(js|css)$~", $fileExtension) == 1) {
        $contentType ='html';
    }
    header("Content-type: $contentType/$fileExtension");
    echo $content;
}