<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Model\Relation;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Enna\Framework\Helper\Str;

/**
 * 远程一对多关联
 * Class HasManyThrough
 * @package Enna\Orm\Model\Relation
 */
class HasManyThrough extends Relation
{
    /**
     * 中间表查询对象
     * @var Query
     */
    protected $through;

    /**
     * 中间表主键
     * @var string
     */
    protected $throughPk;

    /**
     * 中间关联表外键
     * @var string
     */
    protected $throughKey;

    /**
     * HasManyThrough constructor.
     * @param Model $parent 父模型
     * @param string $model 远程模型
     * @param string $through 中间模型
     * @param string $forgienKey 中间模型外键
     * @param string $throughKey 中间关联模型外键
     * @param string $localKey 当前模型主键
     * @param string $throughPk 中间模型主键
     */
    public function __construct(Model $parent, string $model, string $through, string $forgienKey, string $throughKey, string $localKey, string $throughPk)
    {
        $this->parent = $parent;
        $this->model = $model;

        $this->foreignKey = $forgienKey;
        $this->throughKey = $throughKey;

        $this->localKey = $localKey;
        $this->throughPk = $throughPk;

        $this->through = (new $through)->db();
        $this->query = (new $model)->db();
    }

    /**
     * Note: 延迟获取关联数据
     * Date: 2023-06-12
     * Time: 17:35
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 闭包查询条件
     * @return Model\Collection
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $this->baseQuery();

        if ($this->withLimit) {
            $this->query->limit($this->withLimit);
        }

        return $this->query
            ->relation($subRelation)
            ->select()
            ->setParent(clone $this->parent);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-12
     * Time: 17:56
     * @param string $operator 比较操作符
     * @param int $count 个数
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query|null $query Query类型
     * @return Query
     */
    public function has(string $operator = '>=', int $count = 1, string $id = '*', string $joinType = '', Query $query = null)
    {
        $parent = Str::snake(class_basename($this->parent));

        $throughTable = $this->through->getTable();
        $throughPk = $this->throughPk;
        $throughKey = $this->throughKey;

        $relation = new $this->model;
        $relationTable = $relation->getTable();

        $softDelete = $this->query->getOptions('soft_delete');
        $defaultSoftDelete = (new $this->model)->defaultSoftDelete ?: null;

        if ($id != '*') {
            $id = $relationTable . '.' . $relation->getpk();
        }
        $query = $query ?: $this->parent->db()->alias($parent);

        return $query->field($parent . '.*')
            ->join($throughTable, $throughTable . '.' . $this->foreignKey . '=' . $parent . '.' . $this->localKey)
            ->join($relationTable, $relationTable . '.' . $throughKey . '=' . $throughTable . '.' . $throughPk)
            ->when($softDelete, function ($query) use ($softDelete, $relationTable, $defaultSoftDelete) {
                $query->where($relationTable . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
            })
            ->group($relationTable . '.' . $this->throughKey)
            ->having('count(' . $id . ')' . $operator . $count);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-12
     * Time: 18:30
     * @param array|Closure $where 查询条件
     * @param mixed $fields 字段
     * @param string $joinType JOIN类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public function hasWhere($where = [], $fields = null, $joinType = '', Query $query = null)
    {
        $parent = Str::snake(class_basename($this->parent));

        $throughTable = $this->through->getTable();
        $throughPk = $this->throughPk;
        $throughKey = $this->throughKey;

        $modelTable = (new $this->model)->getTable();

        if (is_array($where)) {
            $this->getRelationQueryWhere($where, $modelTable);
        } elseif ($where instanceof Query) {
            $where->via($modelTable);
        } elseif ($where instanceof Closure) {
            $where($this->query->via($modelTable));
            $where = $this->query;
        }

        $fields = $this->getRelationQueryFields($fields, $parent);
        $softDelete = $this->query->getOptions('soft_delete');
        $query = $query ?: $this->parent->db()->alias($parent);
        $defaultSoftDelete = (new $this->parent)->defaultSoftDelete ?: null;

        return $query->join($throughTable, $throughTable . '.' . $this->foreignKey . '=' . $parent . '.' . $this->localKey)
            ->join($modelTable, $modelTable . '.' . $throughKey . '=' . $throughTable . '.' . $this->throughPk, $joinType)
            ->when($softDelete, function ($query) use ($softDelete, $modelTable, $defaultSoftDelete) {
                $query->where($modelTable . strstr($softDelete[0], '.'), '=' == $softDelete[1][0] ? $softDelete[1][1] : $defaultSoftDelete);
            })
            ->group($modelTable . '.' . $this->throughKey)
            ->where($where)
            ->field($fields);
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-13
     * Time: 17:00
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
        $foreignKey = $this->foreignKey;

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$localKey)) {
                $range[] = $result->$localKey;
            }
        }

        if (!empty($range)) {
            $this->query->removeWhereField($foreignKey);

            $data = $this->withWhere([
                [$this->foreignKey, 'in', $range]
            ], $foreignKey, $subRelation, $closure, $cache);

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
     * Date: 2023-06-13
     * Time: 18:01
     * @param Model $result 模型对象
     * @param string $relation 关联对象方法名
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;
        $foreignKey = $this->foreignKey;
        $pk = $result->$localKey;

        $this->query->removeWhereField($foreignKey);

        $data = $this->withWhere([
            [$foreignKey, '=', $pk]
        ], $foreignKey, $subRelation, $closure, $cache);

        if (!isset($data[$pk])) {
            $data[$pk] = [];
        }

        $result->setRelation($relation, $this->resultSetBuild($data[$pk], clone $this->parent));
    }

    /**
     * Note: 关联模型预查询
     * Date: 2023-06-13
     * Time: 17:26
     * @param array $where 关联预查询条件
     * @param string $key 关联键名
     * @param array $subRelation 子查询
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withWhere(array $where, string $key, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $throughList = $this->through->where($where)->select();
        $keys = $throughList->column($this->throughPk, $this->throughPk);

        if ($closure) {
            $this->baseQuery = true;
            $closure($this->getClosureType($closure));
        }

        $throughKey = $this->throughKey;
        if ($this->baseQuery) {
            $throughKey = Str::snake(class_basename($this->model)) . '.' . $this->throughKey;
        }

        $list = $this->query
            ->where($throughKey, 'in', $keys)
            ->cache($cache[0] ?? null, $cache[1] ?? null, $cache[2] ?? null)
            ->select();

        $data = [];
        $keys = $throughList->column($this->foreignKey, $this->throughPk);
        foreach ($list as $set) {
            $key = $keys[$set->{$this->throughKey}];

            if ($this->withLimit && isset($data[$key]) && count($data[$key]) >= $this->withLimit) {
                continue;
            }

            $data[$key][] = $set;
        }

        return $data;
    }

    /**
     * Note: 关联统计:生成统计属性
     * Date: 2023-06-14
     * Time: 9:56
     * @param Model $result 数据对象
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return mixed
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

        $alias = Str::snake(class_basename($this->model));
        $throughTable = $this->through->getTable();
        $pk = $this->throughPk;
        $throughKey = $this->throughKey;
        $modelTable = $this->parent->getTable();

        if (strpos($field, '.') === false) {
            $field = $alias . '.' . $field;
        }

        return $this->query
            ->alias($alias)
            ->join($throughTable, $throughTable . '.' . $pk . '=' . $alias . '.' . $throughKey)
            ->join($modelTable, $throughTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
            ->where($throughTable . $this->foreignKey, '=', $result->$localKey)
            ->$aggreate($field);
    }

    /**
     * Note: 关联统计:子查询
     * Date: 2023-06-14
     * Time: 10:41
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

        $alias = Str::snake(class_basename($this->model));
        $throughTable = $this->through->getTable();
        $pk = $this->throughPk;
        $throughKey = $this->throughKey;
        $modelTable = $this->parent->getTable();

        if (strpos($field, '.') === false) {
            $field = $alias . '.' . $field;
        }

        return $this->query
            ->alias($alias)
            ->join($throughTable, $throughTable . '.' . $pk . '=' . $alias . '.' . $throughKey)
            ->join($modelTable, $modelTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
            ->where($throughTable . '.' . $this->foreignKey . '=' . $modelTable . $this->localKey)
            ->fetchSql()
            ->$aggreate($field);
    }

    /**
     * Note: 执行基础查询
     * Date: 2023-06-12
     * Time: 16:35
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery) && $this->parent->getData()) {
            //父模型
            $parentTable = $this->parent->getTable();

            //中间模型
            $throughTable = $this->through->getTable();
            $throughPk = $this->throughPk;
            $throughKey = $this->throughKey;

            //关联模型
            $alias = Str::snake(class_name($this->model));

            if ($this->withoutField) {
                $this->query->withoutField($this->withoutField);
            }

            $fields = $this->getQueryFields($alias);

            $this->query
                ->field($fields)
                ->alias($alias)
                ->join($throughTable, $throughTable . '.' . $throughPk . '=' . $alias . '.' . $throughKey)
                ->join($parentTable, $parentTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
                ->where($throughTable . '.' . $this->foreignKey, '=', $this->parent->{$this->localKey});

            $this->baseQuery = true;
        }
    }
}