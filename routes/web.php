<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/admin', function () {
    return view('admin');
});
Route::post('/user/login', function () {
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token'
            ],
        'code'=>20000
    ];
});
Route::get('/user/info', function () {
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token'
            ],
        'code'=>20000
    ];
});
Route::get('/transaction/list', function () {
    return [
        'data'=>
            [
                'roles'=> ['admin'],
                'introduction'=> 'I am a super administrator',
                'avatar'=> 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
                'name'=> 'Super Admin',
                'token'=>'admin-token',
                'items'=>[]
            ],
        'code'=>20000
    ];
});

Route::get('/user/logout', function () {
    return [
        'data'=>'success',
        'code'=>20000
    ];
});