<?php
namespace App\Models\VideoVisa;

/**
 * 系统配置表
 */
class FastSystemConfig extends VideoVisaModel
{
    protected $table = 'fast_system_config';
    public $timestamps = false;

    /**
     * 项目新增system config唯一入口
     * @param $data
     * @return mixed
     */
    public function insertSystemLog($data)
    {
        return $this->insertGetId($data);
    }
}