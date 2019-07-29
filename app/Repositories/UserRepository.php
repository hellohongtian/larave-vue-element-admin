<?php
namespace App\Repositories;

use App\Library\RedisObj;
use App\Models\Xin\Users;
use Illuminate\Support\Facades\Redis;

class UserRepository
{
    public $user_model = null;

    const ROLE_GUEST = 0;  //普通用户
    const ROLE_ADMIN = 1;   //管理员
    const ROLE_SEAT = 2;    //坐席
    const ROLE_ROOT = 3;    //超级管理员

    #角色类型
    const FLAG_PUB = 0; //普通角色
    const FLAG_SUPER = 1; //超级管理员
    const FLAG_ADMIN = 2; //管理员
    const FLAG_RISK = 3; //风控


    const LOGIN_SESSION = 'fast_youxinjinrong_login_session_';
    const QRCODE_SESSION = 'fast_youxinjinrong_qrcode_';

    //超级管理员
    public static $root = [
//        'lihongtian'
    ];

    public function __construct()
    {
        $this->user_model = new Users();
    }

    //获取单条信息
    public function getMasterInfo($mastername){
        $res = $this->user_model
            ->select(['masterid','deptname','email','fullname','mastername','mobile','cityid'])
            ->where('mastername','=',$mastername)
//            ->where('status', '=', 1)     //经过培峰确认，暂时不使用status进行过滤
            ->first();
        if ($res) {
            $res = $res->toArray();
        }
        return $res;
    }

    //从LDAP获取一条用户数据
    public function getLdapInfo($mastername){
        $ldap = config('database.ldap');
        // 连上有效的 LDAP 服务器
        $connect = ldap_connect($ldap['host'], $ldap['port']);
        ldap_set_option($connect, LDAP_OPT_PROTOCOL_VERSION, 3);
        // 系住 LDAP 目录，返回true/false
        $bind = @ldap_bind($connect, $ldap['user'], $ldap['pass']);
        if ($bind) {
            // 设置搜索条件
            $filter = 'samaccountname=' . $mastername;
            $attributes = array(
                'samaccountname',
                'cn',
                'mobile',
                'mail',
                'department'
            );
            // 根据条件列出树状简表
            $result = ldap_search($connect, $ldap['dn'], $filter, $attributes);
            // 取得全部返回资料
            $info = ldap_get_entries($connect, $result);
            $count = isset($info['count'])?$info['count']:0;
            if($count < 1){
                return [];
            }
            $ret['mastername'] = isset($info[0]['samaccountname'][0]) ? $info[0]['samaccountname'][0] : '';
            $ret['fullname'] = isset($info[0]['cn'][0]) ? $info[0]['cn'][0] : '';
            $ret['mobile'] = isset($info[0]['mobile'][0]) ? $info[0]['mobile'][0] : '';
            $ret['email'] = isset($info[0]['mail'][0]) ? $info[0]['mail'][0] : '';
            $ret['deptname'] = isset($info[0]['department'][0]) ? $info[0]['department'][0] : '';
            return $ret;
        }
    }

    // 检测数据库当中有没有当前邮箱对应的用户
    public function checkUser($where){
        $ret = $this->user_model->getOne(['masterid'],$where);
        return $ret;
        if ($ret) {
            return true;
        }else{
            return false;
        }
    }

    //新增一个用户
    public function addUsers($data){
        if(!$data) return [];
        $insert = $this->user_model->insertGetId($data);
        return $insert ? $insert : 0;
    }

    /**
     * 是否是管理员
     * @return bool
     */
    public static function isAdmin()
    {
        return session('uinfo.flag') == self::FLAG_ADMIN;
    }

    /**
     * 是否是坐席
     * @return bool
     */
    public static function isSeat()
    {
//        return session('uinfo.role') == self::ROLE_SEAT;
        return session('uinfo.flag') == self::FLAG_RISK;
    }

    /**
     * 是否普通用户
     * @return bool
     */
    public static function isGuest()
    {
//        return session('uinfo.role') == self::ROLE_GUEST;
        return session('uinfo.flag') == self::FLAG_PUB;
    }
    /**
     * 是否超级管理员
     * @return bool
     */
    public static function isRoot()
    {
        return session('uinfo.flag') == self::FLAG_SUPER;
    }

    /**
     * 是否可以领取操作单子
     * @return bool
     */
    public static function canHandleVisa()
    {
        $result = false;

        //只有坐席和特定的开发人员可以接单哦
        if (self::isSeat() || self::isDeveloper()) {
            $result = true;
        }

        return $result;
    }

    public static function isDeveloper()
    {
        return in_array(session('uinfo.mastername'), self::getDeveloperList());
    }

    public static function getDeveloperList()
    {
        return [
            'lihongtian',
        ];
    }

    /**
     * 清除当前的用户登录绑定
     */
    public static function deleteLoginSessionOnRedis()
    {
        RedisObj::instance()->delete(self::getLoginSessionKey());
    }

    /**
     * 在redis中关联该用户和sessionid
     */
    public static function setLoginSessionOnRedis()
    {
        session_start();
        RedisObj::instance()->setex(self::getLoginSessionKey(), session_id());
    }

    public static function setLoginSessionOnRedisNoStart()
    {
        RedisObj::instance()->setex(self::getLoginSessionKey(), session_id());
    }

    /**
     * 获取当前用户关联的最新的sessionid
     * @return bool|mixed|string
     */
    public static function getLoginSessionIdOnRedis()
    {
        return RedisObj::instance()->get(self::getLoginSessionKey());
    }

    public static function getLoginSessionKey()
    {
        return self::LOGIN_SESSION . session('uinfo.mastername');
    }

    public static function setQrcodeUserNameOnRedis($sessionId, $userName) 
    {
        return RedisObj::instance()->setex(self::QRCODE_SESSION.$sessionId, $userName);
    }

    public static function getQrcodeUserNameOnRedis() 
    {
        session_start();
        return RedisObj::instance()->get(self::QRCODE_SESSION.session_id());
    }

    public static function deleteQrcodeUserNameOnRedis() 
    {
        RedisObj::instance()->delete(self::QRCODE_SESSION.session_id());
    }
}
