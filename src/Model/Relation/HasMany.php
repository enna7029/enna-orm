<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Closure;

class HasMany extends Relation
{
    /**
     * HasMany constructor.
     * @param Model $parent 上级模型对象
     * @param string $model 模型名
     * @param string $foreignKey 外键:从属模型名+_id
     * @param string $localKey 主键:从属模型主键
     */
    public function __construct(Model $parent, string $model, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->model = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
        $this->query = (new $model)->db();

        if (get_class($parent) === $model) {
            $this->selfRelation = true;
        }
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-25
     * Time: 17:22
     * @param string $operator 比较运算符
     * @param int $count 数量
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query|null $query 查询对象
     * @return Query
     */
    public function has(string $operator = '>=', int $count = 1, string $id = '*', string $joinType = 'INNER', Query $query = null)
    {
        $table = $this->query->getTable();

        $model = class_basename($this->parent);
        $relation = class_basename($this->model);

        if ($id != '*') {
            $id = $relation . '.' . (new $this->model)->getPk();
        }

        $softDelete = $this->query->getOptions('soft_delete');
        $query = $query ? $query->alias($model) : $this->parent->db()->alias($model);
        $defaultSoftDelete = (new $this->model)->defaultSoftDelete ?: null;

        return $query->field($model . '*')
            ->join([$table => $relation], $model . '.' . $this->localKey . '=' . $relation . '.' . $this->foreignKey, $joinType)
            ->when($softDelete, function ($query) use ($softDelete, $relation, $defaultSoftDelete) {
                $query->where($relation . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
            })
            ->group($relation . '.' . $this->foreignKey)
            ->having('count(' . $id . ')' . $operator . $count);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-22
     * Time: 18:59
     * @param array|Closure $where 条件
     * @param string $fields 字段
     * @param string $joinType Join类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public function hasWhere($where = [], $fields = null, string $joinType = '', Query $query = null)
    {
        $table = $this->query->getTable();
        $model = class_basename($this->parent);
        $relation = class_basename($this->model);

        if (is_array($where)) {
            $this->getRelationQueryWhere($where, $relation);
        } elseif ($where instanceof Query) {
            $where->via($relation);
        } elseif ($where instanceof Closure) {
            $where($this->query->via($relation));
            $where = $this->query;
        }

        $fields = $this->getRelationQueryFields($fields, $relation);
        $softDelete = $this->query->getOptions('soft_delete');
        $query = $query ? $query->alias($model) : $this->parent->db()->alias($model);
        $defaultSoftDelete = (new $this->model)->defaultSoftDelete ?: null;

        return $query->group($model . '.' . $this->localKey)
            ->field($fields)
            ->join([$table => $relation], $model . '.' . $this->localKey . '=' . $relation . '.' . $this->foreignKey, $joinType ?: $this->joinType)
            ->when($softDelete, function ($query) use ($softDelete, $relation, $defaultSoftDelete) {
                $query->where($relation . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
            })
            ->where($where);
    }

    /**
     * Note: 执行基础查询
     * Date: 2023-05-25
     * Time: 18:16
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            if (isset($this->parent->{$this->localKey})) {
                $this->query->where($this->foreignKey, '=', $this->parent->{$this->localKey});
            }

            $this->baseQuery = true;
        }
    }
}