<?php
namespace App\Http\Middleware;

use App\Repositories\Async\AsyncInsertRepository;
use Closure;
use Illuminate\Http\Request;

class OperationLog
{
    public function handle(Request $request, Closure $next)
    {
        $response =  $next($request);
        $uri = $request->capture()->getPathInfo();
        $seatId = session('uinfo.seat_id', -2);
        (new AsyncInsertRepository())->pushOperationLog($seatId, $uri);
        return $response;
    }
}