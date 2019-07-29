<?php
namespace App\Http\Controllers\Api\TecentCloud;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Validator;

class TecentCloudController extends BaseController 
{
    protected $request;
    protected $config_name;
    protected $class_name;
    protected $func_name;
    protected $allow_method_list;
    protected $check_params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->config_name = 'tecentCloud';

        $url_path = parse_url($this->request->url())['path'];
        $cf_arr = explode('/', $url_path);
        $request_path = $cf_arr[3];

        $config_params = config($this->config_name.".".$request_path);

        if(!empty($config_params)) {
            $this->class_name = $config_params['class_belong'];
            $this->allow_method_list = $config_params['allow_method'];
            $this->func_name = $config_params['callback_func'];
            $this->check_params = array();
            if(!empty($config_params['params'])) {
                $this->check_params = $config_params['params'];
            }

            if(!empty($config_params['middleware'])) {
                $middleware_arr = $config_params['middleware'];
                foreach ($middleware_arr as $middleware_name) {
                    $this->middleware($middleware_name);
                }
            }
        }
    }

    public function controllerDistribute() {

        try {
            if(empty($this->class_name) || empty($this->func_name)) {
                return response(['code' => -1,'data' => array(),'msg' => 'wrong url,check again']);
            }

            $input_method = strtoupper($this->request->method());
            if(!in_array($input_method, $this->allow_method_list)) {
                return response(['code' => -1,'data' => array(),'msg' => 'method is not allowed']);
            }

            $all_input_params = $this->request->all();
            if(!empty($this->check_params)) {
                $validator = Validator::make(
                    $all_input_params, $this->check_params
                );
                if ($validator->fails()) {
                    return response(['code' => -1, 'msg' => $validator->messages()]);
                }
            }

            $func_params = array();
            foreach ($this->check_params as $key => $value) {
                $func_params[$key] = NULL;
                if(array_key_exists($key, $all_input_params)) {
                    $func_params[$key] = $all_input_params[$key];
                }
            }
            if($input_method == "POST") {
                $func_params = array($func_params);
            }

            $class_name = "App\Repositories\Api\TecentCloud\\".$this->class_name;
            $class_obj = new $class_name();

            //todo 记录日志
            $logContent = "Class[".$this->class_name."], Func[".$this->func_name."], params[".json_encode($func_params)."] begin exec";

            $result = call_user_func_array(array($class_obj, $this->func_name), $func_params);

            $logContent = "Class[".$this->class_name."], Func[".$this->func_name."], params[".json_encode($func_params)."] exec over and result is ".json_encode($result);

            return response()->json($result);
        }
        catch(\Exception $e) {
            //todo 记录日志
            return ['code' => -1, 'msg' => "操作失败[".$e->getMessage()."]"];
        }
    }
}