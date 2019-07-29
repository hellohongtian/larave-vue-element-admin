<?php

namespace App\Library;

class ImageHelper{


    public static function uri2URL($uri) {
        // 空URI 返回空字符串
        if (empty($uri)) {
            return '';
        }

        $imgHostList = [
            'http://c1.xinstatic.com',
            'http://c2.xinstatic.com',
            'http://c3.xinstatic.com',
            'http://c4.xinstatic.com',
            'http://c5.xinstatic.com',
            'http://c6.xinstatic.com',
        ];
        $imgHost = $imgHostList[rand(0, 5)];

        return $imgHost . '/' . trim($uri, '/');
    }
}