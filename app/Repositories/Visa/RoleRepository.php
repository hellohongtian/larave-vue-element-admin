<?php
namespace App\Repositories\Visa;

use App\Fast\FastException;
use App\Library\Common;
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
use phpDocumentor\Reflection\Types\Self_;

class RoleRepository
{

    protected $role_model;
    protected static $role_static_list=[];

    public function __construct()
    {
        $this->role_model = new Role();
    }


    public function get_role_list()
    {
        if(!self::$role_static_list){
            $res = $this->role_model->getAll(['roleid','name'],['status' => 1]);
            self::$role_static_list = array_column($res,'name','roleid');
        }
        return self::$role_static_list;
    }


}