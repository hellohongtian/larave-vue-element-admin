<?php
namespace App\Http\Controllers\Test;

use App\Models\VideoVisa\RbacMasterComment;
use App\XinApi\CreditApi;
use DB;
use App\Http\Controllers\BaseController;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Models\VideoVisa\FastVisa;
use Illuminate\Http\Request;
use App\Repositories\Api\InitialFaceRepository;

class TestController extends BaseController {

    public $redis;
    public $neteaseRep;
    public $person_credit;
    protected $signature = 'cron:alitest';
    protected $description = '测试连接';
    protected $error_email = 'lihongtian@xin.com';
    public function test(){
        dd((new FastVisa())->whereRaw('id > 100')->selectRaw(' id,full_name as 姓名')->get()->toArray());
//        _dump(DB::select('select * from fast_visa where id =100'));
    }
    /**
     *  mysql 配置 '数据库名' => array表示连主库,false为从库
     * @return array
     */
    private function mysql_config() {
        return array(
            'video_visa'=>[
                'insert' => "insert into admin (fullname) VALUES('alitest')",
                'select1' => "SELECT fullname,mobile FROM admin WHERE fullname = 'alitest'",
                'update' => "UPDATE admin set mobile = 1234567890 where fullname = 'alitest'",
                'select2' => "SELECT fullname,mobile FROM admin WHERE fullname = 'alitest'",
                'delete' => "DELETE from admin where fullname = 'alitest'",
                'select3' => "SELECT fullname,mobile FROM admin WHERE fullname = 'alitest'",
            ],
            'xin'=>false,
            'xin_credit'=>false,
            'newcar'=>false,
            'finance'=>false,
            'xinpay'=>false,
            'risk_stat'=>false,
            'sys_finance'=>false,
        );
    }

    /**
     *  redis 配置 '数据库名' => 是否连接主库
     * @return array
     */
    private function redis_config() {
        return array(
            'redis',
        );
    }

    /**
     * @return array
     */
    private function need_check()
    {
        return  array(
            'mysql_config',
            'redis_config'
        );
    }

    public function __construct()
    {
        parent::__construct();
//        if(!is_private_ip() || time()>strtotime('2019-05-20')){
//        if(time()>strtotime('2019-05-20')){
//            exit('not allow view!');
//        }
    }

    public function alitest()
    {
        var_dump(get_ip());
        exit();
        if(empty($this->need_check())){
            $this->_out('无需检验,停止...',true);
        }
        try {
            $need_check = $this->need_check();
            foreach ($need_check as $value){
                $config_name = $this->$value();
                foreach ($config_name as $k => $v) {
                    switch ($value){
                        case 'redis_config':
                            $this->_out("-------------------------开始检查 [redis] 配置 -> {$v}-------------------------");
                            $keyName = "database.".$v.".default";
                            $redis_c = C($keyName);
                            $params = empty($redis_c)? '': [$redis_c];
                            if(!$params){
                                throw new \Exception("{$k}配置不存在");
                            }
                            break;
                        default :
                            $this->_out("-------------------------开始检查 [mysql] 配置 -> {$k}-------------------------");
                            $keyName = "mysql.".$k;
                            if(is_array($v)){
                                $params = [$keyName,$v];
                            }else{
                                $params = [$keyName];
                            }

                    }
                    call_user_func_array([$this,$value."_check"],$params);
                    $this->_out("{$keyName}配置pass...",true);
                }
            }
            $this->_out("Successful!");
        }catch (\Exception $e){
            $msg = $e->getMessage();
            $trace = $e->getTraceAsString();
            @Common::sendMail('配置异常','错误信息'.'<p>msg:' . $msg . '<p>trace:' . $trace , $this->error_email);
            $this->_out('配置异常'.'错误信息'.'<p>msg:' . $msg . '<p>trace:' . $trace,true);
        }
    }

    /**
     * @param $keyName
     * @param $isMaster
     */
    private function mysql_config_check($keyName, $isMaster=[])
    {
        $obj = DB::connection($keyName);
        if($isMaster){ //主库
//            $obj = $obj->setReadPdo(null);
            foreach ($isMaster as $kk => $vv){
                $res = json_encode($obj->select($vv, [], false));
                $tips = substr($kk,0,6) === 'select'? ',返回结果->'.$res:'';
                $this->_out("[mysql] 执行动作->{$kk},语句->{$vv}{$tips}");
                sleep(1);
            }
        }
        $res = $obj->select('show tables');
        $res = empty($res)? '异常':'正常';
        $this->_out("[mysql] 执行语句->show tables,返回结果是否正常->{$res}");

    }

    /**
     * @param $config
     */
    private function redis_config_check($config)
    {
        $cache = RedisCommon::init(false,'fast_redis_group_test',$config);
        $r_key = 'apli_test_key';
        $data = array(
            'test'=>'我是数据'
        );
        $this->_out("[redis] 写入测试...");
        $cache->set($r_key,$data,3600);
        sleep(1);
        $get= $cache->get($r_key);
        $this->_out("[redis] 写入后数据获取测试,返回结果->{$get['test']}");
        $get = $cache->delete($r_key);
        $this->_out("[redis] 删除测试,返回结果->{$get}");
        $get_delete= $cache->get($r_key);
        $this->_out("[redis] 删除后数据获取测试,返回结果->$get_delete");
    }

    private function _out($str, $type = false ){
        if(!is_cli()){
            if(!headers_sent()){
                set_header();
            };
            $string = '';
            $string.='<hr  style=" border:none; border-bottom:1px solid #999;" /><pre>';
            foreach (func_get_args() as $item) {
                if($item === true){
                    continue;
                }
                $string.=print_r($item,true);
                $string.='<br />';
            }
            $string.='</pre>';
            echo $string.'<br />';
        }else{
            switch ($type) {
                case false:
                    echo "\033[1;30;47;9m$str \e[0m\n";
                    break;
                default:
                    echo "\033[1;37;42;9m$str \e[0m\n";
            }
        }
    }

    function face(Request $request){
        $applyid = $request->input('applyid','');
        $key = $request->input('key','');
        $server= $request->input('server',false);
        if($key != '88888888'){
            echo '禁止访问!';
            exit;
        }
        if($server){
            dd($_SERVER);
        }
        if(empty($applyid)){
            $str = '3721951,3722060,3722057,3722033,3721955,3722044,3722156,3722147,3722209,3722143';
        }
        $applyid = explode(',',$str);
        foreach ($applyid as $v){
            $status = (new InitialFaceRepository())->credit_info($v);
            echo 'apply_id为->'.$v.',返回信审结果为'.$status.PHP_EOL;
            if($status){
                (new FastVisa())->updateBy(['erp_credit_status'=>intval($status)],['apply_id'=>$v]);
            }
        }
        exit();
    }

    function mailTest(){
        if(!is_private_ip() && is_production_env()){
            return header_location('/');
        }
        $a = Notify()->set('send mail temp 77777','vvvvvv')->send();
        var_dump($a);
        _dump('-----------直连请求发送邮箱测试-----------');
        $a2 = api_send_mail('shenxin@xin.com','api send mail','api_send mail_contentr');
        var_dump($a2);
        _dump('-----------直连请求测试-----------');
        /*  
        $a3 = Notify('mail')->set('ssssssss notuify send mail',array('titkle'=>'aaaaaaaaaaaaaaaaa'))->send();
        var_dump($a3);
        $a4 = Notify('wx')->set('','wx push send data test')->send();
        var_dump($a4);
        $a5 = Notify('sms')->set('','短信内容推送消息！！！')->send();
        var_dump($a5);*/
        return;
    }
}