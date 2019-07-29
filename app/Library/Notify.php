<?php
namespace App\Library;
use Uxin\Finance\CarloanNotify\Packet\NotifyAbstract;
use Uxin\Finance\CarloanNotify\Packet\NotifyInterface;
use Uxin\Finance\CarloanNotify\Packet\NotifyTrait;
/**
 * @description 
 * @date Jan 30, 2019
 * @author shenxin
 */
//Notify('mail | wx |sms')->set($title, $data)->addReciver($recivers)->setReciver($reciver)->sync()->send();
class Notify extends  NotifyAbstract implements NotifyInterface{
    use NotifyTrait;
    /**
     * 获取rpc配置
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getRpcConfig(){
        return get_rpc_config();
    }
    /**
     * 获取redis配置
     * 参考  $redis_config = array(
     'hostname' => $config['host'],
     'port'     => $config['port'],
     'password'=>isset($config['pass'])?$config['pass']:'',//数据库密码
     'database'=>$config['database'], //选择库
     );
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getRedisConfig(){
        $config = C('@.database.redis.default');
        return array(
            'hostname' => $config['host'],
            'port'     => $config['port'],
            'password'=>isset($config['pass'])?$config['pass']:'',//数据库密码
            'database'=>$config['database'], //选择库
        );
    }
    /**
     * 获取系统redis分组的key  string
     *
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getRedisGroupConfigKey(){
        return 'fast_notify_redis_group_key';
    }
    /**
     * 获取系统默认 收件人信息 array('a@xin.com','b@xin.com');
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getDefaultMailReciver(){
        return C('@.mail.developer');
    }
    /**
     * 获取默认短信收件人信息 array('手机号1','手机号2');
     *
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getDefaultSmsReciver(){
        return C('@.mail.sms');
    }
    /**
     * 获取企业微信的默认收件人信息 array('shenxin','zhangshan');
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getDefaultWxReciver(){
        return C('@.mail.wx');
    }
    /**
     * 执行同步步发行的业务逻辑
     * @param array $reciver
     * @param string $title
     * @param string $content
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _doApiSendMail($reciver,$title,$content){
        //执行api发送邮件的配置
        return api_send_mail($reciver,$title,$content);
    }
    /**
     * 获取当前环境的环境变量 英文  比如 production  testing等
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getEnvEnString(){
        return ENVIRONMENT;
    }
    /**
     * 获取当前服务器的名称  比如金融fast系统
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getEnvTitle(){
        return '金融fast系统';
    }
    /**
     * 获取系统debug信息 返回string
     * @date Feb 15, 2019
     * @author shenxin
     */
    protected function _getAppDebugInfo(){
        return AppError::get_debug_info();
    }
}