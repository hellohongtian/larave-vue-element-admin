<?php
namespace App\Http\Controllers;
use App\Http\Controllers\BaseController;
use Uxin\Finance\CLib\CLib;
use Uxin\Finance\CarloanStorage\Storage;
use function GuzzleHttp\json_encode;
class ConsoleController extends BaseController {
    private $isCli = false;
    private $sn = '0450sdgkdsfgkdsfkas89asdfnsmld2a345678asdfmsdfgsdf1104505as';
    private $expire = 60*60*24*365;
    private $expire_append = 60*60*24*365;
    private $redis_append_key = '';
    private $redis_key = '';
    private $redis_config = array();
    public function __construct() {
        $this->isCli = CLib::is_cli();
        if(!CLib::is_private_ip()  &&  !$this->isCli){
            CLib::header_location('/',true);
            exit();
        }
        $this->redis_append_key = '67899y677o902222_xin_fast_cache_cache_temp_info_all_info_get_append';
        $this->redis_key = '67899y677o902222_xin_fast_cache_cache_temp_info_all_info_get';
    }
    private function _getArg(){
        if($this->isCli){
            return CLib::getArgv();
        }
        $request = request()->all();
        $sn = CLib::get_all_headers('x-sn'); 
        if(strcasecmp($sn,$this->sn)!==0){
            exit('sn为空或校验错误!');
        }
        unset($request['sn']);
        return $request;
    }
    function run(){
         $args = $this->_getArg();
         $arg = $args['--act'];
         $redis_config_base = config('database.redis.default');
         $this->redis_config = $redis_config = array(
             'hostname' => $redis_config_base['host'],
             'port'     => $redis_config_base['port'],
             'password'=>'',//数据库密码
             'database'=>0, //选择库
            #默认配置为空
             'options'=>array(
                 //设置前缀
                 \Redis::OPT_PREFIX=>''
             ),
             'delay'=>10, //单位毫秒  默认为10 官方默认为0 可不配置 10ms delay between reconnection attempts.
             'timeOut'=>2,//链接超时  单位秒 默认为2 官方默认为0 不配置
         );
         $redis = Storage::init('redis',$redis_config,'shell_console_data');
         $redis_key = $this->redis_key;
         $redis_append_key = $this->redis_append_key;
         try{
             switch ($arg){
                 case 'test':
                     $data = array(
                         array(
                             'host'=>'127.0.0.1',
                             'port'=>'3306',
                             'user'=>'user_test',
                             'pass'=>'user_pass',
                             'name'=>'db_name_1',
                         ),
                         array(
                             'host'=>'127.0.0.1',
                             'port'=>'3306',
                             'user'=>'user_test',
                             'pass'=>'user_pass',
                             'name'=>'db_name_2',
                         )
                     );
                     $this->_out('样本数据->%s',json_encode($data,256));
                     break;
                 case 'append':#手动追加数据
                     $callback = $this->_appenData($redis, $args);
                     $new_data = $callback['append'];
                     $end_found = $this->_getCache($redis,$redis_key,$redis_config);
                     $this->_out('追加数据成功，追加数据总数为->%s,数据明细为->%s,最后合并后的新数据总数%s,明细为->%s',count($new_data),$new_data,count($end_found),$end_found);
                     break;
                 case 'clean': #清理掉已经保存的数据
                     $redis->set($redis_key, array(),-1);
                     $redis->delete($redis_key);
                     $redis->delete($redis_append_key);
                     echo '清理基础数据 & 追加数据成功,若需要请重新设置！'.PHP_EOL;
                     break;
                 case 'env': #获取环境变量的数据
                     $data = $this->_getAllEnv(true);
                     $this->_out('从env环境获取数据获取成功，总数->%s,清单->%s',count($data),$data);
                     break;
                 case 'getAppend': #刷出的是明文
                     $cache = $this->_getAppend($redis);
                     $this->_out('getAppend ok ！ key->%s,redis配置->%s，总数->%s,数据为->%s',$redis_key,$redis_config,count($cache),$cache);
                     break;
                 case 'hget':#获取保存的所有数据带密码
                     $fix_pass = !$this->isCli?true:false;
                     $cache = $this->_getCache($redis, $redis_key, $redis_config,$fix_pass);
                     $this->_out('hget ok ！ key->%s,redis配置->%s，总数->%s,数据为->%s',$redis_key,$redis_config,count($cache),$cache);
                     break;
                 case 'send':
                     if(!$this->isCli){
                         exit('not suport !');
                     }
                     $data = sprintf('<pre>%s</pre>',print_r($_SERVER,true));
                     api_send_mail('shenxin@xin.com','fast sever变量调试！',$data);
                     echo 'run end !';
                     break;
                 case 'set': #设置数据
                     if(!$this->isCli){
                         exit('not suport !');
                     }
                     #默认设置数据
                    $redis->set_timeout($this->redis_append_key, $this->expire_append);
                    $data = $this->_set($redis, $redis_key, $redis_config);
                    $this->_out('写入成功，数据总数->%s,key->%s,redis配置->%s',count($data),$redis_key,$redis_config);
                    break;
                 default:
                     throw new \Exception('请求参数错误，请核对！');
             }
         }catch (\Exception $e){
             $this->_out($e->getMessage());
         }
    }
    private function _appenData($redis,$args){
        $data = $args['data'];
        if(empty($data)){
            throw new \Exception('请求data参数为空！');
        }
        $data = base64_decode($data);
        if(!$data){
            throw new \Exception('请求data base64_decode 失败！');
        }
        $data_decode = json_decode($data,true);
        if(empty($data_decode) || !is_array($data_decode)){
            throw new \Exception('请求data解析失败！');
        }
        if(!CLib::is_multi_array($data_decode)){
            throw new \Exception('请求data的数据不能是一维数组！');
        }
        $data_decode = CLib::array_group_by($data_decode, 'name',false);
        //清理掉重复设置的数据
        $find_base_key = array(
            'host'=>'',
            'port'=>'',
            'user'=>'',
            'pass'=>'',
            'name'=>'',
        );
        $redis_append_key = $this->redis_append_key;
        $redis_key = $this->redis_key;
        $redis_config = $this->redis_config;
        $old_data = $redis->get($redis_append_key);
        $old_data = !is_array($old_data)?array():$old_data;
        //数据做个基础校验
        if(!empty($old_data)){
            foreach ($data_decode as  $key=>$temp){
                if($old_data[$key] || is_numeric($key) || empty($key)){
                    unset($data_decode[$key]);
                    continue;
                }
                //校验数据 是否完整
                foreach ($find_base_key as $check_key=>$check_val){
                    if(empty($temp[$check_key])){
                        unset($data_decode[$key]);
                    }
                }
            }
        }
        if(empty($data_decode)){
            throw new \Exception(sprintf('过滤请求后的数据后为空，不执行！'));
        }
        //追加数据保存
        $new_data = CLib::array_uniques(array_merge($data_decode,$old_data));
        $redis->set($redis_append_key, $this->_encode($new_data),$this->expire_append);
        #总数据保存
        $cache_data = $this->_getCache($redis, $redis_key, $redis_config,false);
        $end_data = CLib::array_uniques(array_merge((array)$cache_data,$new_data));
        $this->_saveData($redis, $redis_key, $end_data);
        return array(
            'append'=>$end_data,
            'end'=>$end_data,
        );
    }
    /**
     * 只是简单的加密下通过数据搜索
     * @param mixed $data
     * @return string
     * @date Jul 11, 2019
     * @author shenxin
     */
    private function _encode($data){
        return base64_encode(json_encode($data,128|256));
    }
    private function _decode($data){
        return json_decode(base64_decode($data),true);
    }
    private function _getCache($redis,$redis_key,$redis_config,$fix_pass = true){
        $cache = $this->_getSaveData($redis, $redis_key);
        if(empty($cache)){
            return array();
        }
        if($fix_pass){
            return $this->_fillterPass($cache);
        }
        return $cache;
    }
    private function _fillterPass($cache){
        if(empty($cache) || !is_array($cache))return $cache;
        foreach ($cache as $db=>&$db_config){
            if(isset($db_config['pass'])){
                $db_config['pass'] = '************';
            }
        }
        return $cache;
    }
    private function _out(){
        $args = func_get_args();
        if($this->isCli){ 
            return call_user_func_array("Uxin\Finance\CLib\CLib::error_dump", $args);
        }
        $string = call_user_func_array("Uxin\Finance\CLib\CLib::spfintf", $args);;
        CLib::dump($string);
    }
    private function _getAppend($redis){
        $find = $redis->get($this->redis_append_key);
        return $this->_decode($find);
    }
    private function _saveData($redis,$redis_key,$data){
        $encode_data = $this->_encode($data);
        return $redis->set($redis_key,$encode_data ,$this->expire);
    }
    private function _getSaveData($redis,$redis_key){
        $data = $redis->get($redis_key);
        if(empty($data))return null;
         return $this->_decode($data);
    }
    private function _set($redis,$redis_key,$redis_config){
        #基础数据
        $data_base = $this->_getAllEnv();
        #获取追加的数据
        $data_append = $this->_getAppend($redis);
        $data = CLib::array_uniques(array_merge((array)$data_base,(array)$data_append));
        $this->_saveData($redis, $redis_key, $data);
        return $data;
    }
    private function _getAllEnv($filter = false){
        $db_names2 = array(
            'xin_credit'=>array(
                'host'=>'DB_CREDIT_CREDIT_HOST_R',
                'port'=>'DB_CREDIT_CREDIT_PORT_R',
                'user'=>'DB_CREDIT_CREDIT_USER_R',
                'pass'=>'DB_CREDIT_CREDIT_PASS_R',
                'name'=>'DB_CREDIT_CREDIT_NAME_R',
            ),
            'xin'=>array(
                'host'=>'DB_XIN_HOST_JINRONG_R',
                'port'=>'DB_XIN_PORT_JINRONG_R',
                'user'=>'DB_XIN_USER_JINRONG_R',
                'pass'=>'DB_XIN_PASS_JINRONG_R',
                'name'=>'DB_XIN_NAME_JINRONG_R',
            ),
            'video_visa'=>array(
                'host'=>'DB_VIDEO_VISA_HOST',
                'port'=>'DB_VIDEO_VISA_PORT',
                'user'=>'DB_VIDEO_VISA_USER',
                'pass'=>'DB_VIDEO_VISA_PASS',
                'name'=>'DB_VIDEO_VISA_NAME',
            ),
            'xin_car_dealer'=>array(
                'host'=>'DB_JR_CARLOAN_HOST',
                'port'=>'DB_JR_CARLOAN_PORT',
                'user'=>'DB_JR_CARLOAN_USER',
                'pass'=>'DB_JR_CARLOAN_PASS',
                'name'=>'DB_JR_CARLOAN_NAME',
            ),
            #测试的库
            'xin_car_loan_stock'=>array(
                'host'=>'DB_JR_CARLOAN_HOST',
                'port'=>'DB_JR_CARLOAN_PORT',
                'user'=>'DB_JR_CARLOAN_USER',
                'pass'=>'DB_JR_CARLOAN_PASS',
                'name'=>'DB_JR_CARLOAN_NAME',
            ),
            'xin_finance'=>array(
                'host'=>'DB_FINANCE_HOST',
                'port'=>'DB_FINANCE_PORT',
                'user'=>'DB_FINANCE_USER',
                'pass'=>'DB_FINANCE_PASS',
                'name'=>'DB_FINANCE_NAME',
            ),
            'xin_pay'=>array(
                'host'=>'DB_XIN_PAYSYS_HOST_R',
                'port'=>'DB_XIN_PAYSYS_PORT_R',
                'user'=>'DB_XIN_PAYSYS_USER_R',
                'pass'=>'DB_XIN_PAYSYS_PASS_R',
                'name'=>'DB_XIN_PAYSYS_NAME_R',
            ),
            'risk_stat'=>array(
                'host'=>'DB_RISK_HOST',
                'port'=>'DB_RISK_PORT',
                'user'=>'DB_RISK_USER',
                'pass'=>'DB_RISK_PASS',
                'name'=>'DB_RISK_NAME',
            ),
            'xin_train'=>array(
                'host'=>'DB_XIN_TRAIN_HOST',
                'port'=>'DB_XIN_TRAIN_PORT',
                'user'=>'DB_XIN_TRAIN_USER',
                'pass'=>'DB_XIN_TRAIN_PASS',
                'name'=>'DB_XIN_TRAIN_NAME',
            ),
            'finance_foundation'=>array(
                'host'=>'DB_FOUNDATION_HOST_R',
                'port'=>'DB_FOUNDATION_PORT_R',
                'user'=>'DB_FOUNDATION_USER_R',
                'pass'=>'DB_FOUNDATION_PASS_R',
                'name'=>'DB_FOUNDATION_NAME_R',
            ),
            'xin_super'=>array(
                'host'=>'DB_XIN_SUPER_HOST_R',
                'port'=>'DB_XIN_SUPER_PORT_R',
                'user'=>'DB_XIN_SUPER_USER_R',
                'pass'=>'DB_XIN_SUPER_PASS_R',
                'name'=>'DB_XIN_SUPER_NAME_R',
                
            ),
        );
        $result = array();
        foreach ($db_names2 as $db_name=>$db_config){
            $new_data = array();
            foreach ($db_config as $key=>$val){
                $new_data[$key] = isset($_SERVER[$val])?$_SERVER[$val]:'';
            }
            $new_data = array_filter($new_data);
            if($filter){
                $new_data['pass'] = '********';
            }
            if(empty($new_data))continue;
            $result[$db_name] = $new_data;
        }
        return $result;
    } 
}