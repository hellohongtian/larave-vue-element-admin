<?php
/**
 * Created by PhpStorm.
 * #2019-02-13 调整类到 核心控制类里去 支持获取日志等信息 shenxin
 * User: changke
 * Date: 2016/11/21
 * Time: 下午7:37
 */
namespace App\Library;
use Uxin\Finance\CarloanStorage\Packages\RedisAdapter;
use Uxin\Finance\CarloanStorage\Storage;
class RedisCommon
{
    private $rq_conf;
    public $redisq;
    //兼容老代码
    public function __construct($use_pconnect = false,$group = 'fast_redis_group_default',$config = array())
    {
        $config = empty($config)?$this->_getRedisConfig():$config;
        $group = empty($group)?'fast_redis_group_default':$group;
        $redis_config = array(
            'hostname' => $config['host'],
            'port'     => $config['port'],
            'password'=>isset($config['pass'])?$config['pass']:'',//数据库密码
            'database'=>$config['database'], //选择库
            'options'=>array(
                //设置前缀
                #Redis::OPT_PREFIX=>self::CREDIT_SYSTEM_REDIS_PREFIX
            ),
            'delay'=>5, //单位毫秒  默认为10 官方默认为0 可不配置 10ms delay between reconnection attempts.
            'timeOut'=>2,//链接超时  单位秒 默认为2 官方默认为0 不配置 
        );
        $object = RedisAdapter::init();
        $md5_hash = md5(json_encode(array('redis',$redis_config,$group),256));
        $object->setConfig($redis_config)->setHash($md5_hash)->set_pconnect((boolean)$use_pconnect);
        $this->redisq = $object;
        $object = null;
        return $this;
    }
    /**
     * 
     * @return \Uxin\Finance\CarloanStorage\Packages\RedisAdapter
     * @date Feb 13, 2019
     * @author shenxin
     */
    function getAdapter(){
        return  $this->redisq;
    }
    /**
     * @return \Redis
     * @date Feb 13, 2019
     * @author shenxin
     */
    public function getConnect(){
        return  $this->redisq;
    }
    private function _getRedisConfig(){
        return $this->rq_conf = C('@.database.redis.default');
    }
    public static function init($use_pconnect = false,$group = 'fast_redis_group_default',$config= array()){
        $hash = md5(json_encode(array($use_pconnect,$group,$config)));
        static $config_hash = array();
        if(!is_object($config_hash[$hash])){
            return $config_hash[$hash] = new self($use_pconnect,$group,$config);
        }
        return $config_hash[$hash];
    }
    public  function debug($to_array = false,$format_list = true,$building_html = true){
        return Storage::debug($to_array,$format_list,$building_html);
    }
    /**
     *
     * @param string $key
     */
    private function _get($key)
    {
        return unserialize($this->redisq->rPop($key));
    }

    /**
     *
     * @param string $key
     * @param mixed $data
     */
    private function _set($key, $data)
    {
        return $this->redisq->lPush($key, serialize($data));
    }

    /**
     * 从队列取数据，可取多条
     * @param string $key queue name
     * @param integer $num record count
     * @return string|multitype:NULL
     */
    public function qget($key, $num = 1)
    {
        if (!$key || $num < 1) {
            return "";
        }
        if ($num == 1) {
            return $this->_get($key);
        }
        $results = array();
        for ($i = 0; $i < $num; $i++) {
            $r = $this->_get($key);
            if (!$r)
                break;
            $results[] = $r;
        }
        return $results;
    }

    /**
     * 入队列 ， 可多条
     * @param string $key queue name
     * @param mixed $data data
     * @param boolean $multi 是否多条
     * @return boolean
     */
    public function qset($key, $data, $multi = false)
    {
        if (!$key || !$data) {
            return false;
        }
        if ($multi && is_array($data)) {
            foreach ($data as $d) {
                $this->_set($key, $d);
            }
        } else {
            $this->_set($key, $data);
        }
        return true;
    }

    /**
     * 取队列里数据
     * @param $key
     * @param int $start
     * @param int $end
     * @return array|mixed|string
     */
    public function lrange($key, $start = 0, $end = -1)
    {
        $data = $this->redisq->lRange($key, $start, $end);
        if ($data) {
            if (is_array($data)) {
                $r = array();
                foreach ($data as $item) {
                    $r[] = unserialize($item);
                }
                return $r;
            } else
                return unserialize($data);
        } else
            return "";
    }

    public function setex($key, $value, $timeout = 86400)
    {
        $value = serialize($value);
        return $this->redisq->setex($key, $timeout, $value);
    }

    public function setnx($key, $value)
    {
        return $this->redisq->setnx($key, serialize($value));
    }

    public function get($key,$type=0)
    {
        $s = $this->redisq->get($key);
        if($s){
            if($type == 0){
               return unserialize($s); 
           }else{
                return $s;
           }
        }
        return false;
    }

    public function set($key,$val)
    {
        $this->redisq->set($key,serialize($val));
    }

    public function delete($key)
    {
        return $this->redisq->delete($key);
    }

    /**
     * 判断对象是否锁定，如果没有锁，即加锁
     * @param $obj  对象 可以为任意类型 除resource类型外
     * @param int $locktime 锁定秒数
     * @return bool true 已经锁定  false 没有锁定，并锁定
     */
    public function is_lock($obj, $locktime = 3)
    {
        $key = md5(json_encode($obj));
        if ($this->get($key))
            return true;
        else {
            $this->setex($key, $obj, $locktime);
            return false;
        }
    }

    /*
     * 向名称为$key的集合中添加元素$member
     * @param $key
     * @param $member
     * @return $int the number of elements added to the set
     */

    public function sadd($key, $member)
    {
        return $this->redisq->sAdd($key, $member);
    }

    /*
     * 删除名称为$key的集合中的元素$member
     * @param $key
     * @param $member
     * @return $int The number of elements removed from the set
     */

    public function srem($key, $member)
    {
        return $this->redisq->sRem($key, $member);
    }

    /**
     * 判断$member是否是名称为$key的集合的元素
     * @param $key
     * @param $member
     * @return boolean
     */
    public function sismember($key, $member)
    {
        return $this->redisq->sIsMember($key, $member);
    }

    /**
     * 求交集
     * @param $key1
     * @param $key2
     * @return array
     */
    public function sinter($key1, $key2)
    {
        return $this->redisq->sInter($key1, $key2);
    }

    /**
     * 获取集合中的所有成员
     * @param $key
     * @return array
     */
    public function smembers($key)
    {
        return $this->redisq->sMembers($key);
    }

    /*
     * @desc 设置超时时间
     */
    public function expire($key, $time)
    {
        return $this->redisq->expire($key, $time);
    }

    /*
     * @desc 计数器加1
     */
    public function incr($key){
        return $this->redisq->incr($key);
    }

    /*
     * @desc 获取$key的超时时间
     *
     * return -1|-2
     * The command returns -2 if the key does not exist.
     * The command returns -1 if the key exists but has no associated expire.
     */
    public function ttl($key)
    {
        return $this->redisq->ttl($key);
    }

    /**
     * 添加一个或者多个值到有序集合中
     * @param $key
     * @param $score
     * @param $value
     * @param null $score2
     * @param null $value2
     * @param null $scoreN
     * @param null $valueN
     * @return int
     */
    public function zadd($key, $score, $value, $score2 = null, $value2 = null, $scoreN = null, $valueN = null){
        return $this->redisq->zAdd($key, $score, $value,$score2, $value2, $scoreN, $valueN);
    }

    public function zRange($key, $start, $end, $withscores = null ){
        return $this->redisq->zRange($key, $start, $end, $withscores);
    }

    public function zRem($key, $member1, $member2 = null, $memberN = null){
        return $this->redisq->zRem($key, $member1, $member2, $memberN);
    }

    public function zCard($key){
        return $this->redisq->zCard($key);
    }
    public function zRemRangeByScore($key, $start, $end){
        return $this->redisq->zRemRangeByScore($key, $start, $end);
    }
    public function zRangeByScore( $key, $start, $end, array $options = array() ){
        return $this->redisq->zRangeByScore( $key, $start, $end,$options);
    }

    public function zScore( $key, $member )
    {
        return $this->redisq->zScore( $key, $member );
    }

}