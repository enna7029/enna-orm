<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Closure;

/**
 * 一对多关联类
 * Class HasMany
 * @package Enna\Orm\Model\Relation
 */
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
     * Note: 延迟获取关联数据
     * Date: 2023-06-07
     * Time: 11:02
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @return Model\Collection
     */
    public function getRealtion(array $subRelation = [], Closure $closure = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        if ($this->withLimit) {
            $this->query->limit($this->withLimit);
        }

        return $this->query
            ->where($this->foreignKey, '=', $this->parent->{$this->localKey})
            ->relation($subRelation)
            ->select()
            ->setParent(clone $this->parent);
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-07
     * Time: 11:48
     * @param array $resultSet 数据集
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 是否关联缓存
     * @return void
     */
    public function withQuerySet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$localKey)) {
                $range[] = $result->$localKey;
            }
        }

        if (!empty($range)) {
            $data = $this->withOneToMany([
                [$this->foreignKey, 'in', $range]
            ], $subRelation, $closure, $cache);

            foreach ($resultSet as $result) {
                $pk = $result->$localKey;

                if (!isset($data[$pk])) {
                    $data[$pk] = [];
                }

                $result->setRelation($relation, $this->resultSetBuild($data[$pk], clone $this->parent));
            }
        }
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-07
     * Time: 11:45
     * @param Model $result 数据对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 是否关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;

        if (isset($result->$localKey)) {
            $data = $this->withOneToMany([
                [$this->foreignKey, '=', $result->$localKey]
            ], $subRelation, $closure, $cache);

            if (!isset($data[$result->$localKey])) {
                $data[$pk] = [];
            }

            $result->setRelation($relation, $this->resultSetBuild($data[$result->$localKey], clone $this->parent));
        }
    }

    /**
     * Note: 关联模型预查询
     * Date: 2023-06-07
     * Time: 14:58
     * @param array $where 关联预查询条件
     * @param array $subRelation 子关联模型方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withOneToMany(array $where, array $subRelation, Closure $closure = null, array $cache = [])
    {
        $foreignKey = $this->foreignKey;

        $this->query->removeWhereField($foreignKey);

        if ($closure) {
            $this->baseQuery = true;
            $closure($this->getClosureType($closure));
        }

        if ($this->withoutField) {
            $this->query->withoutField($this->withoutField);
        }

        $list = $this->query
            ->where($where)
            ->cache($cache[0] ?? false, $cache[1] ?? false, $cache[2] ?? false)
            ->with($subRelation)
            ->select();

        $data = [];
        foreach ($list as $set) {
            $key = $set->$foreignKey;

            if ($this->withLimit && isset($data[$key]) && count($data[$key]) >= $this->withLimit) {
                continue;
            }

            $data[$key][] = $set;
        }

        return $data;
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
     * Note: 关联统计
     * Date: 2023-06-07
     * Time: 16:21
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
     * Note: 创建关联统计子查询
     * Date: 2023-06-07
     * Time: 16:38
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

        return $this->query->alias($aggreate . '_table')
            ->whereExp($aggreate . '_table' . $this->foreignKey, '=', $this->parent->getTable() . '.' . $this->localKey)
            ->fetchSql()
            ->$aggreate($field);
    }

    /**
     * Note: 保存(新增)当前关联数据对象
     * Date: 2023-06-07
     * Time: 16:41
     * @param Model|array $data 数据:模型对象或数组
     * @param bool $replace 是否自动识别更新和写入
     * @return Model|false
     */
    public function save($data, bool $replace = true)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }

        $model = new $this->model;
        $data[$this->foreignKey] = $this->parent->{$this->localKey};

        return $model->replace($replace)->save($data) ? $model : false;
    }

    /**
     * Note: 批量保存(新增)当前关联数据对象
     * Date: 2023-06-07
     * Time: 16:50
     * @param iterable $dataSet 数据集
     * @param bool $replace 是否自动识别更新或写入
     * @return array|false
     */
    public function saveAll(iterable $dataSet, bool $replace = true)
    {
        $result = [];
        foreach ($dataSet as $key => $data) {
            $result[] = $this->save($data, $replace);
        }

        return empty($result) ? false : $result;
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