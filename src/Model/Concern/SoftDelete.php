<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;

/**
 * 数据软删除
 * @mixin Model
 */
trait SoftDelete
{
    /**
     * 包含软删除数据
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Note: 覆盖被父类的方法
     * Date: 2023-11-10
     * Time: 14:44
     * @param array $scope 设置不使用的全局查询范围
     * @return mixed
     */
    public function db($scope = [])
    {
        $query = parent::db($scope);
        $this->withNoTrashed($query);

        return $query;
    }

    /**
     * Note: 判断当前实例是否被软删除
     * Date: 2023-05-17
     * Time: 9:55
     * @return bool
     */
    public function trashed()
    {
        $field = $this->getDeleteTimeField();

        if ($field && !empty($this->getOrigin($field))) {
            return true;
        }

        return false;
    }

    /**
     * Note: 查询时,默认排除软删除数据
     * Date: 2023-05-12
     * Time: 16:20
     * @param Query $query
     * @return void
     */
    public function withNoTrashed(Query $query)
    {
        $field = $this->getDeleteTimeField(true);

        if ($field) {
            $condition = is_null($field) ? ['null', ''] : ['=', $this->defaultSoftDelete];
            $query->useSoftDelete($field, $condition);
        }
    }

    /**
     * Note: 查询软删除数据
     * Date: 2023-05-17
     * Time: 9:57
     * @return Query
     */
    public static function withTrashed()
    {
        $model = new static();

        return $model->db()->removeOption('soft_delete');
    }

    /**
     * Note: 只查询软删除数据
     * Date: 2023-05-17
     * Time: 16:11
     * @return Query
     */
    public function onlyTrashed()
    {
        $model = new static();
        $field = $model->getDeleteTimeField(true);

        if ($field) {
            return $model->db()->useSoftDelete($field, $this->getWithTrashedExp());
        }

        return $model->db();
    }

    /**
     * Note: 只查询软删除数据的条件
     * Date: 2023-05-17
     * Time: 16:17
     * @return array
     */
    protected function getWithTrashedExp()
    {
        return is_null($this->defaultSoftDelete) ? ['not null', ''] : ['<>', $this->defaultSoftDelete];
    }

    /**
     * Note: 删除当前记录
     * Date: 2023-05-19
     * Time: 17:09
     * @return bool
     */
    public function delete()
    {
        if (!$this->isExist() || !$this->ieEmpty() || $this->trigger('BeforeDelete') === false) {
            return false;
        }

        $field = $this->getDeleteTimeField();
        $force = $this->isForce();

        if ($field && !$force) {
            $this->set($field, $this->autoWriteTimestamp());

            $result = $this->exist()->withEvent(false)->save();

            $this->withEvent(true);
        } else {
            $where = $this->getWhere();

            $result = $this->db()
                ->where($where)
                ->removeOption('soft_delete')
                ->delete();

            $this->lazySave(false);
        }

        //关联删除
        if (!empty($this->relationWrite)) {
            $this->autoRelationDelete($force);
        }

        $this->trigger('AfterDelete');

        $this->exists(false);

        return true;
    }

    /**
     * Note: 删除记录
     * Date: 2023-05-20
     * Time: 10:24
     * @param mixed $data 主键列表,支持闭包查询条件
     * @param bool $force 是否强制删除
     * @return bool
     */
    public static function destroy($data, bool $force = false)
    {
        if (empty($data) && $data !== 0) {
            return false;
        }

        $model = new static();
        if ($force) {
            $model->withTrashedData(true);
        }
        $query = $model->db(false);

        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof \Closure) {
            call_user_func_array($data, [&$query]);
            $data = null;
        } elseif (is_null($data)) {
            return false;
        }

        $resultSet = $query->select($data);
        foreach ($resultSet as $result) {
            $result->force($force)->delete();
        }

        return true;
    }

    /**
     * Note: 恢复软删除的数据
     * Date: 2023-05-20
     * Time: 11:04
     * @param array $where
     */
    public function restore($where = [])
    {
        $field = $this->getDeleteTimeField();

        if (!$field || $this->trigger('BeforeRestore') === false) {
            return false;
        }

        if (empty($where)) {
            $pk = $this->getPk();
            if (is_string($pk)) {
                $where[] = [$pk, '=', $this->getData($pk)];
            }
        }

        $this->db(false)
            ->where($where)
            ->useSoftDelete($field, $this->getWithTrashedExp())
            ->update([$field => $this->defaultSoftDelete]);

        $this->trigger('AfterRestore');

        return true;
    }

    /**
     * Note: 获取软删除字段
     * Date: 2023-05-12
     * Time: 16:25
     * @param bool $read 是否查询操作
     * @return string|false
     */
    protected function getDeleteTimeField(bool $read = false)
    {
        $field = property_exists($this, 'delete_time') && isset($this->deleteTime) ? $this->deleteTime : 'delete_time';

        if ($field == false) {
            return false;
        }

        if (strpos($field, '.') === false) {
            $field = '__TABLE__.' . $field;
        }

        if (!$read && strpos('.', $field)) {
            $array = explode('.', $field);
            $field = array_pop($array);
        }

        return $field;
    }
}