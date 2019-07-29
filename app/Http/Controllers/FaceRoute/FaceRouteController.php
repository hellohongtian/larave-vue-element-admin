<?php
namespace App\Http\Controllers\FaceRoute;


use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;

/**
 * 面签路由控制器
 */
class FaceRouteController extends BaseController
{

    public function index(){
        return view('face_route.index');
    }


}