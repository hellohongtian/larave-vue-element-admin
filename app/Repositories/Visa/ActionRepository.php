<?php
namespace App\Repositories\Visa;

use App\Fast\FastException;
use App\Library\Common;
use App\Library\RedisCommon;
use App\Models\VideoVisa\Action;
use App\Models\VideoVisa\FastVisa;
use App\Models\VideoVisa\FastVisaLog;
use App\Models\VideoVisa\FastVisaResult;
use App\Models\VideoVisa\NetEase\FastVideoData;
use App\Models\VideoVisa\Role;
use App\Models\VideoVisa\SeatManage;
use App\Models\Xin\CarHalfApply;
use App\Models\XinCredit\PersonCredit;
use App\Models\XinCredit\PersonCreditResult;
use App\Repositories\BaseRepository;
use App\Repositories\CityRepository;
use App\Repositories\CommonRepository;
use App\Repositories\FastVisaRepository;
use App\Repositories\SeatManageRepository;
use App\Repositories\UserRepository;
use App\User;
use App\Models\XinFinance\CarHalfService;
use DB;

class ActionRepository extends BaseRepository
{

    protected $action_model;
    #缓存菜单key
    const FAST_USE_MENU_LIST_KEY = "fast_user_menu_key_for_";

    public function __construct(Action $action)
    {
        $this->action_model = $action;
    }

    /**
     * 构造左边树菜单
     */
    public static function get_menu_list($items=[],$pid ="parent_actionid")
    {
        $cache = RedisCommon::init()->get(self::FAST_USE_MENU_LIST_KEY.session('uinfo.seat_id'));
        if($cache){
            return $cache;
        }
        if(empty($items)){
            $items = (new Action())->select('*')->where('status',1)->orderBy('orderid')->get()->toarray();
        }
        $map  = [];
        $tree = [];
        //数据的ID名生成新的引用索引树
        foreach ($items as &$it){ $map[$it['actionid']] = &$it; }
        foreach ($items as &$it){
            //在map里面查找每一项的pid父级对应的项，这里新生成了一个$parent变量,找到对应的pid项，引用过来后,判断此项是否存在,也就是说如果，父级项存在，就在此父级项$parent中写入 son字数据
            $parent = &$map[$it[$pid]];
            if($parent) {
                $parent['son'][] = &$it;
            }else{
                //最后如果判断的parent父级项不存在，那个直接添加到tree结果中。一级目录
//                $tree[] = &$it;
                $tree = &$it;
            }
        }
        RedisCommon::init()->set(self::FAST_USE_MENU_LIST_KEY.session('uinfo.seat_id'),$tree);
        return $tree;
    }
    /**
     * 构造左边树菜单
     * @param bool $new
     * @return mixed
     */
    public static function get_menu_list_new( $new = false )
    {
        $cache = RedisCommon::init()->get(self::FAST_USE_MENU_LIST_KEY.session('uinfo.seat_id'));
        if($cache && !$new){
            return $cache;
        }

        $roleid = (new SeatManage())->getOne(['roleid'],['id'=>session('uinfo.seat_id')])['roleid'];
        $actions = (new Role())->getOne(['role_data'],['roleid'=>$roleid])['role_data'];
        if($actions){
            $actions = json_decode($actions,true);
        }
        //增加白名单菜单
        $mastername = session('uinfo.mastername');

        DB::setFetchMode(\PDO::FETCH_ASSOC);
        $res = DB::select("select * from action where type = 1 and white_list is not null");
        $temp_actions = [];
        if ($res) {
            array_map(function ($v) use ($mastername,&$temp_actions) {
                if ($mastername && in_array($mastername, explode(',', $v['white_list']))) {
                    $temp_actions[] = $v;
                }
            }, $res);
        }

        //有当前用户的白名单配置时
        if($temp_actions){
            $action_obj = new Action();
            foreach ($temp_actions as $value) {
                $flag = false;
                $parent_id = $value['parent_actionid'];
                foreach ($actions['son'] as $k => $v) {

                    if($v['actionid'] == $parent_id){
                        if (empty($v['son'])) {
                            $actions['son'][$k]['son'][] = $value;
                        }else{
                            $count = count($v['son']);
                            foreach ($v['son'] as $index => $temp) {
                                if (($count-1 == $index) || ($temp['orderid'] >= $value['orderid'] )) {
                                    array_splice( $actions['son'][$k]['son'],$index+1,0,[$value]);
                                }else{
                                    continue;
                                }
                            }
                        }
                        $flag = true;
                    }
                }
                //没有找到根菜单时
                if(!$flag){
                    $res = $action_obj->getOne(['*'],['actionid' => $parent_id]);
                    $res['son'][] = $value;
                    $count_temp = count($actions['son']);
                    foreach ($actions['son'] as $kk => $vv) {
                        if (($count_temp - 1 == $kk) || ($vv['orderid'] >= $res['orderid'])) {
                            array_splice($actions['son'],$kk+1,0,[$res]);
                        } else {
                            continue;
                        }

                    }
                }
            }
        }
        RedisCommon::init()->set(self::FAST_USE_MENU_LIST_KEY.session('uinfo.seat_id'),$actions);
        return $actions;
    }

    /**
     * 获取左侧菜单需要的action
     * @return array
     */
    public  function get_action_list_for_left()
    {
        return $this->action_model->select('*')->where('status',1)->orderBy('orderid')->get()->toarray();
    }

    /**
     * 获取权限action数组
     * array:9 [▼
     * 0 => "/"
     * 1 => "/user/index"
     * 2 => "/system/index"
     * 3 => "/seat-manage/reset_list"
     * 4 => "/seat-manage/analysis"
     * 5 => "/analysis/index"
     * 6 => "/fast-visa/wait_list"
     * 7 => "/fast-visa/check_list"
     * 8 => "/fast-visa/all_list"
     * ]
     * @param string $check
     * @return mixed
     */
    public static function get_action_list_for_auth($check = '')
    {
        if($check && (UserRepository::isRoot() || UserRepository::isAdmin())){
            return true;
        }
        $data = RedisCommon::init()->get(ActionRepository::FAST_USE_MENU_LIST_KEY . session('uinfo.seat_id'));
        if($data){
            $actions = self::get_action_from_role_data($data);
            $mastername = session('uinfo.mastername');
            DB::setFetchMode(\PDO::FETCH_ASSOC);
            $res = DB::select("select white_list,uri from action where  white_list is not null");
            if ($res) {
                array_map(function ($v) use ($mastername,&$actions) {
                    if ($mastername && in_array($mastername, explode(',', $v['white_list']))) {
                        $actions[] = $v['uri'] !== '/' ?trim($v['uri']):$v['uri'];
                    }
                }, $res);
            }
            return $check? in_array($check!=='/'? trim($check):$check, $actions):$actions;
        }
    }
    public static function get_action_from_role_data($data)
    {
        static $action_list = [];
        $action_list = array_filter(array_unique(array_merge($action_list,array_column($data,'uri'))));
            foreach ($data as $key=>$value){
                if($key == 'son'){
                    self::get_action_from_role_data($value);
                }else if(!empty($value['son'])){
                    self::get_action_from_role_data($value['son']);
                }
            }
        return $action_list;
    }
    /**
     * 判断是否有权限
     * @param $uri
     * @return bool
     */
    public static function is_have_auth($uri)
    {
        if(UserRepository::isAdmin() || UserRepository::isRoot()){
            return true;
        }
        if(empty($uri)){
            return false;
        }
        $uri = trim($uri);
        $res = (new Action())->getOne(['white_list'],['uri' => $uri]);
        $mastername = session('uinfo.mastername');
        if(!empty($res) && $mastername && in_array($mastername,explode(',',$res['white_list']))){
            return true;
        }
        return false;
    }
}