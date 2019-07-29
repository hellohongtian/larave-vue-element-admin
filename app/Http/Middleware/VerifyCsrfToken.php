<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
//        'im/saveinfo',
//        'im/wz_face_upload_file',
//        'im/callback',
        'common/*',
        'im/*',
        'Netease/*',
        'qrcode/*',
        'fast-visa/all_list',
        'console/*'
    ];
}
