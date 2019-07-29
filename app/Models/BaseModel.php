<?php
/**
 * 通用Model类
 * Class XinModel
 * @package App\Models
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BaseModel extends Model
{
    /**
     * 通用分页查询列表方法
     * @param array $fields
     * @param array $where
     * @param array $orderBy
     * @param array $groupBy
     * @param int $pageSize
     * @param bool $useWritePdo
     */
    public function getList($fields = [], $where = [], $orderBy = [], $groupBy = [], $pageSize = 100,$useWritePdo = false)
    {

        if(!is_array($fields)) {
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
        }

        $query = $this->select($fields);
        //强制读主库
        if ($useWritePdo) {
            $query->useWritePdo();
        }
        $query = $this->createWhere($query, $where, $orderBy, $groupBy);
        $result = $query->paginate($pageSize);
        $result->setPath('');

        return $result;
    }
    
    /**
     * 通用查询列表方法,查询全部
     * @param array $fields
     * @param array $where
     * @param array $orderBy
     * @param array $groupBy
     * @param bool $isArray 类型,默认是数组,对象可以传入obj
     * @param bool $useWritePdo 类型默认是false 读从库
     * @param int $limit
     * @return mixed 返回数组
     */
    public function getAll($fields = [], $where = [], $orderBy = [], $groupBy = [], $isArray = true, $useWritePdo = false,$limit = 0)
    {
        if(!is_array($fields)) {
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
        }

        $query = $this->select($fields);
        //强制读主库
        if ($useWritePdo) {
            $query->useWritePdo();
        }
        $query = $this->createWhere($query, $where, $orderBy, $groupBy);
        if($limit != 0){
            $query = $query->limit($limit);
        }
        $result = $query->get();

        if ($isArray) {
            $result = $result->toArray();
        }

        return $result;
    }

    /**
     * 通用获取单条记录方法
     * @param array $fields
     * @param array $where
     * @param array $orderBy
     * @param boolean $useWritePdo 类型默认是false 读从库
     * @return array
     */
    public function getOne($fields = [], $where = [], $orderBy = [], $useWritePdo = false)
    {
        if(!is_array($fields)) {
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
        }

        $query = $this->select($fields);
        //强制读主库
        if ($useWritePdo) {
            $query->useWritePdo();
        }
        $query = $this->createWhere($query, $where, $orderBy);

        $result = $query->first();

        $result = $result ? $result->toArray() : [];

        return $result;
    }


    /**
     * 根据条件统计总数
     * @param array $where
     * @param array $orderBy
     * @param array $groupBy
     * @return array
     */
    public function countBy($where = [], $orderBy = [], $groupBy = [])
    {

        $query = $this->createWhere($this, $where, $orderBy , $groupBy);

        $result = $query->count();

        return $result;
    }
    /**
     * 根据条件统计总数
     * @param array $where
     * @param array $orderBy
     * @param array $groupBy
     * @return array
     */
    public function countByNew($where = [], $orderBy = [], $groupBy = [],$isArray = false)
    {

        $query = $this->createWhere($this, $where, $orderBy , $groupBy);
        if ($isArray) {
            $query = $query->toArray();
        }
        $result = $query->get()->count();
        return $result;
    }

    /**
     * 根据条件更新数据
     * @param $data
     * @param array $where
     */
    public function updateBy($data, $where)
    {
        $query = $this->createWhere($this, $where);
        return $query->update($data);
    }

    /**
     * 根据条件删除数据
     * @param array $where
     */
    public function deleteBy($where)
    {
        $query = $this->createWhere($this, $where);
        return $query->delete();
    }

    /**
     * 设置where条件
     * @param $query
     * @param array $where
     * @param array $orderBy
     * @param array $groupBy
     * @return mixed
     */
    public function createWhere($query, $where =[], $orderBy = [], $groupBy = [])
    {
        if(isset($where['in'])) {
            foreach($where['in'] as $k => $v) {
                $query = $query->whereIn($k, $v);
            }
            unset($where['in']);
        }
        if(isset($where['not_in'])) {
            foreach($where['not_in'] as $k => $v) {
                $query = $query->whereNotIn($k, $v);
            }
            unset($where['not_in']);
        }
        if(isset($where['raw'])) {
            foreach($where['raw'] as $k => $v) {
                $query = $query->whereRaw($v);
            }
            unset($where['raw']);
        }

        if($where){
            foreach ($where as $k => $v) {
                $operator = '=';
                if (substr($k, -2) == ' <') {
                    $k = trim(str_replace(' <', '', $k));
                    $operator = '<';
                } elseif (substr($k, -3) == ' <=') {
                    $k = trim(str_replace(' <=', '', $k));
                    $operator = '<=';
                } elseif (substr($k, -2) == ' >') {
                    $k = trim(str_replace(' >', '', $k));
                    $operator = '>';
                } elseif (substr($k, -3) == ' >=') {
                    $k = trim(str_replace(' >=', '', $k));
                    $operator = '>=';
                } elseif (substr($k, -3) == ' !=') {
                    $k = trim(str_replace(' !=', '', $k));
                    $operator = '!=';
                } elseif (substr($k, -3) == ' <>') {
                    $k = trim(str_replace(' <>', '', $k));
                    $operator = '<>';
                } elseif (substr($k, -5) == ' like') {
                    $k = trim(str_replace(' like', '', $k));
                    $operator = 'like';
                    $v = '%' . $v . '%';
                }
                $query = $query->where($k, $operator, $v);
            }
        }
        if($orderBy) {
            foreach($orderBy as $k => $v) {
                $query = $query->orderBy($k, $v);
            }
        }

        if($groupBy) {
            $query = $query->groupBy($groupBy);
        }
//        echo $query->toSql();

        return $query;
    }

    public function addAll(Array $data)
    {
        $rs = DB::table($this->getTable())->insert($data);
        return $rs;
    }
}
