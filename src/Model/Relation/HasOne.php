<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Closure;

class HasOne extends OneToOne
{
    /**
     * HasOne constructor.
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
    public function has(string $operator = '>=', int $count = 1, string $id = '*', string $joinType = '', Query $query = null)
    {
        $table = $this->query->getTable();
        $model = class_basename($this->parent);
        $relation = class_basename($this->model);
        $localKey = $this->localKey;
        $foreignKey = $this->foreignKey;
        $softDelete = $this->query->getOptions('soft_delete');
        $query = $query ? $query->alias($model) : $this->parent->db()->alias($model);
        $defaultSoftDelete = (new $this->model)->defaultSoftDelete ?: null;

        return $query->whereExists(function ($query) use ($table, $model, $relation, $localKey, $foreignKey, $softDelete, $defaultSoftDelete) {
            $query->table([$table => $relation])
                ->field($relation . '.' . $foreignKey)
                ->whereExp()
                ->when($softDelete, function ($query) use ($softDelete, $relation, $defaultSoftDelete) {
                    $query->where($relation . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
                });
        });
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

        return $query->field($fields)
            ->join([$table => $relation], $model . '.' . $this->localKey . '=' . $relation . '.' . $this->foreignKey, $joinType ?: $this->joinType)
            ->when($softDelete, function ($query) use ($softDelete, $relation, $defaultSoftDelete) {
                $query->where($relation . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
            })
            ->where($where);
    }

    /**
     * Note: 创建关联统计子查询
     * Date: 2023-05-26
     * Time: 11:38
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return string
     */
    public function getRelationAggreateQuery(Closure $closure = null, string $aggreate = 'count', string $field = '*', string &$name = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        return $this->query
            ->whereExp($this->foreignKey, '=', $this->parent->getTable() . '.' . $this->localKey)
            ->fetchSql()
            ->$aggreate($field);
    }

    /**
     * Note: 关联统计
     * Date: 2023-05-26
     * Time: 14:16
     * @param Model $result 数据对象
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return int
     */
    public function getRelationAggreate(Model $result, Closure $closure = null, string $aggreate = 'count', string $field = '*', string &$name = null)
    {
        $localKey = $this->localKey;

        if (!isset($result->$localKey)) {
            return 0;
        }

        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        return $this->query
            ->where($this->foreignKey, '=', $result->$localKey)
            ->$aggreate($filed);
    }

    /**
     * Note: 获取关联数据
     * Date: 2023-05-26
     * Time: 17:42
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包查询方法
     * @return Model
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        $localKey = $this->localKey;

        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $relationModel = $this->query
            ->removeWhereField($this->foreignKey)
            ->where($this->foreignKey, '=', $this->parent->$localKey)
            ->relation($subRelation)
            ->find();

        if ($relationModel) {
            if (!empty($this->bindAttr)) {
                $this->bindAttr($this->parent, $relationModel);
            }

            $relationModel->setParent(clone $this->parent);
        }

        return $relationModel;
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