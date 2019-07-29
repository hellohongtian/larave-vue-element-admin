<?php
/**
 * Created by PhpStorm.
 * User: tanrenzong
 * Date: 18/1/31
 * Time: 下午5:24
 */
namespace App\Http\Controllers;
use App\Http\Controllers\BaseController;
use App\Models\VideoVisa\Admin;
use App\Models\VideoVisa\SeatManage;
use App\Models\VideoVisa\VideoVisaModel;
use App\Models\VideoVisa\VisaRemarkAttach;
use App\Models\VideoVisa\VisaPool;
use App\Repositories\UserRepository;
use DB;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 *
 */
class AdminController extends BaseController {
    public function adminlist(Request $request)
    {
        $request = $request->all();
        $request['pagesize'] = !empty($request['pagesize']) ? $request['pagesize'] : 5;

        $adminModel = new Admin();
        $list = $adminModel->getList(['id', 'mastername', 'fullname', 'deptname',
            'mobile', 'email', 'create_time', 'status'], [], [], [], $request['pagesize']);
        return view('admin.index', [
            'list' => $list,
            'request' => $request,
            'city_list' => []
        ]);
    }

    public function add(Request $request)
    {
        $request = $request->all();
        $adminModel = new Admin();
        $erpMasterId = isset($request['masterid']) ? $request['masterid'] : 0;
        $masterName = isset($request['mastername'])?$request['mastername']:'';
        $fullName = isset($request['fullname']) ? $request['fullname'] : '';
        $email = isset($request['email']) ? $request['email'] : '';
        $mobile = isset($request['mobile']) ? $request['mobile'] : '';
        $deptName = isset($request['deptname']) ? $request['deptname'] : '';

        if (!$email) {
            return $this->showMsg(self::CODE_FAIL,'缺少参数',[]);
        }

        if (!$erpMasterId) {
            return $this->showMsg(self::CODE_FAIL,'请选择员工',[]);
        }

        //是否为坐席
        $isExist = (new SeatManage())->getOne(['id'], ['mastername'=>$masterName, 'status'=>1]);
        if ($isExist) {
            return $this->showMsg(self::CODE_FAIL, '该员工是坐席，不允许同时添加为管理员');
        }

        //是否已是管理员
        $exist = $adminModel->getOne(['id'],['masterid'=>$erpMasterId]);
        if ($exist) {
            return $this->showMsg(self::CODE_FAIL, '管理员' . $fullName . '已存在');
        }

        //是否为超级管理员
        if (in_array($masterName, UserRepository::$root)) {
            return $this->showMsg(self::CODE_FAIL, '该员工为超级管理员');
        }

        $data = [
            'masterid' => $erpMasterId,
            'mastername' => $masterName,
            'fullname' => $fullName,
            'deptname' => $deptName,
            'mobile' => $mobile,
            'email' => $email,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ];
        $id = $adminModel->insertGetId($data);
        return $this->showMsg(self::CODE_SUCCESS);
    }

    /**
     * 修改状态
     * @param Request $request
     * @return mixed
     */
    public function editStatus(Request $request)
    {
        $params = $request->all();
        $adminModel = new Admin();
        $id = isset($params['id']) ? $params['id'] : '';
        $status = isset($params['status']) ? $params['status'] : '';

        if (empty($id) || !in_array($status, [1,2])) {
            return $this->showMsg(self::CODE_FAIL, self::MSG_PARAMS);
        }

        $admin = $adminModel->getOne(['status'], ['id'=>$id]);
        if (!$admin) {
            return $this->showMsg(self::CODE_FAIL, '用户不存在');
        }

        if ($admin['status'] == $status) {
            return $this->showMsg(self::CODE_FAIL, '状态错误');
        }

        $res = $adminModel->updateBy(['status'=>$status, 'update_time'=>time()], ['id' => $id]);
        if (!$res) {
            return $this->showMsg(self::CODE_FAIL, $res);
        }

        return $this->showMsg(self::CODE_SUCCESS, self::MSG_SUCCESS);
    }
}