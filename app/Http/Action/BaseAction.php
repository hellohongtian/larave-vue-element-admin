<?php

namespace App\Http\Action;

use Exception;
use Throwable;
use App\Fast\FastException;
use Illuminate\Http\Request;

abstract class BaseAction {
    public function __construct()
    {

    }

    final public function handle()
    {
        try {
            $this->init();
            $this->beforeRun();
            $result = $this->run();

            $this->outputRet($result);

            $this->afterRun();

        } catch (FastException $e){
            $this->outputError($e->getCode(), $e->getMessage(), $e->getExtra());
        } catch (Exception $e) {
        } catch (Throwable $e) {
            $this->throwExceptionToLaravel($e->getCode(), $e->getMessage());
        } finally{
            $this->finallyRun();
        }
    }

    final protected function init()
    {
        $this->request = Request::capture()->all();
    }

    protected function beforeRun()
    {

    }
    protected function afterRun()
    {

    }
    protected function finallyRun()
    {

    }
    protected function run()
    {
        return false;
    }

    public function outputRet($data){
        echo $this->responseMessage(0, 'ok', $data);
        fastcgi_finish_request();
    }

    private function responseMessage($errorCode = 0, $message = null, $data = null)
    {
        $ret = ['code' => $errorCode, 'msg' => $message, 'data' => $data];

        $returnData = json_encode($ret, JSON_UNESCAPED_UNICODE);
        if(json_last_error() == JSON_ERROR_NONE){
            return $returnData;
        }else{
            $ret = ['code' => -1, 'msg' => 'json非法字符', 'data' => null];
            return json_encode($ret, JSON_UNESCAPED_UNICODE);
        }
    }
}
