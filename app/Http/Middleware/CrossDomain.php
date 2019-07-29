<?php
namespace App\Http\Middleware;

use App\Repositories\Async\AsyncInsertRepository;
use Closure;
use Illuminate\Http\Request;

class CrossDomain
{
    public $allow_origin;
    public $always_allow;

    public function __construct() {
        $this->allow_origin = [];
        $this->always_allow = true;
    }

    public function handle(Request $request, Closure $next)
    {
        $origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';
        if(in_array($origin, $this->allow_origin) || $this->always_allow == true) {
            header('Access-Control-Allow-Origin:' . $origin);
        }
        return $next($request);
    }
}