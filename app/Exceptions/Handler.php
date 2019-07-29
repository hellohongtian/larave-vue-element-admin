<?php

namespace App\Exceptions;

use App\Library\DeBug\DeBug;
use App\Library\Helper;
use App\Models\VideoVisa\ErrorCodeLog;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Library\Common;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Uxin\Finance\CLib\CLib;
use App\Library\AppError;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $e)
    {
        $request = app('Illuminate\Http\Request');
        if (!Helper::isProduction()) {
            $request['debug'] = 'mail';
        }
        $requestParam = $request->all();

        //去除敏感字段
        if (isset($requestParam['password'])) {
            unset($requestParam['password']);
        }
        $content = [
            'method' => $request->method(),
            'request' => json_encode($requestParam),
            'clientIp' => CLib::get_ip(),
            'serverIp' => CLib::get_server_ip(),
            'url' => $request->url(),
            'pathInfo' => $request->path(),
            'errorClass' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'msg' => $e->getMessage(),
            'traceString' => $e->getTraceAsString(),
            'debug' => DeBug::getContent(),
            'time' => date('Y-m-d H:i:s'),
            'debug_info'=>AppError::get_debug_info()
        ];
        $excludedErrors = [
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            #'Illuminate\Session\TokenMismatchException',
            'Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException',
            'Symfony\Component\HttpKernel\Exception\HttpException'
        ];
        if (in_array($content['errorClass'], $excludedErrors)){
            send_http_status(404);
            @header("Content-Type:application/json;charset=utf-8");
            $this->sendExceptionMsg('Page Not Found!');
        }
        $title = AppError::get_title('','handler exception!');
        $content_send = sprintf('<pre>%s</pre>',print_r($content,true));
//        api_send_mail(array(),$title,$content_send);
        if(!Helper::isProduction()){
            _dump($content);
            exit();
        }
        /*
        if (in_array($content['errorClass'], $excludedErrors)){
            send_http_status(404);
            $this->sendExceptionMsg('Page Not Found!');
        }
        try {
            if (Helper::isProduction() || (isset($requestParam['debug']) && $requestParam['debug'] == 'mail')) {
                $excludedErrors = [''];
                $excludedPathInfos = [''];
                if (!in_array($content['errorClass'], $excludedErrors) && !in_array($content['pathInfo'], $excludedPathInfos)) {
                    $title = Helper::isProduction() ? '中央面签API报错' : '[测试]中央面签API报错';
                    $errorCode = isset(config('errorLogCode')[$request->path()]) ? config('errorLogCode')[$request->path()] : config('errorLogCode.defaultApi');
                    $content['errorCode'] = $errorCode; 
                    #Common::sendMail($title, '错误信息: ' . print_r($content, true), config('mail.developer')); 
                    (new ErrorCodeLog())->runLog($errorCode, $content);
                }
            }
        } catch (\Exception $ex) {

        } 
       # _dump($content);
        $content_send = sprintf('<pre>%s</pre>',print_r($content,true));
        api_send_mail(array(),'中央面签API报错 exception ',$content_send);
       # $callback = Notify()->set('中央面签API报错 exception ',$content)->send();
        if(!Helper::isProduction()){
            _dump($content);
            exit();
        }
        if ($e instanceof NotFoundHttpException) {
            abort(403);
        }*/
        parent::report($e);
    }
    
    protected function sendExceptionMsg($title)
    {
        exit(
            json_encode([
                'code' => -1,
                'message' => $title
            ],256)
            );
    }

    
    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            $resData = [
                'code' => -1,
                'msg' => $exception->getMessage(),
                'data' => []
            ];
            return response()->json($resData);
        }

        return response()->view('errors.403', ['msg'=>$exception->getMessage()]);

//        if ($request->expectsJson()) {
//            return response()->json(['error' => 'Unauthenticated.'], 401);
//        }
//
//        return redirect()->guest('login');
    }
}
