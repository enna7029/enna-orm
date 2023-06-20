<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Model;
use Enna\Orm\Model\Collection;
use Enna\Orm\Model\Relation;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;

/**
 * 多态一对多关联
 * Class MorphMany
 * @package Enna\Orm\Model\Relation
 */
class MorphMany extends Relation
{
    /**
     * 多态字段:外键
     * @var string
     */
    protected $morphKey;

    /**
     * 多态字段:类型
     * @var string
     */
    protected $morphType;

    /**
     * 多态类型
     * @var string
     */
    protected $type;

    /**
     * MorphMany constructor.
     * @param Model $parent 父模型
     * @param string $model 当前模型名
     * @param string $morphKey 多态字段:外键
     * @param string $morphType 多态字段:类型
     * @param string $type 多态类型
     */
    public function __construct(Model $parent, string $model, string $morphKey, string $morphType, string $type)
    {
        $this->parent = $parent;
        $this->model = $model;

        $this->morphKey = $morphKey;
        $this->morphType = $morphType;
        $this->type = $type;

        $this->query = (new $model)->db();
    }

    /**
     * Note: 延迟获取关联数据
     * Date: 2023-06-17
     * Time: 17:14
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 闭包查询条件
     * @return Collection
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        if ($this->withLimit) {
            $this->query->limit($this->withLimit);
        }

        $this->baseQuery();

        return $this->query->relation($subRelation)
            ->select()
            ->setParent(clone $this->parent);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-17
     * Time: 17:19
     * @param string $operator 比较操作符
     * @param int $count 个数
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public function has(string $operator = '>=', int $count = 1, string $id = '*', string $joinType = '', Query $query = null)
    {
        throw new DbException('relation not support: has');
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-17
     * Time: 17:20
     * @param array $where 查询条件（数组或者闭包）
     * @param null $fields 字段
     * @param string $joinType JOIN类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public function hasWhere($where = [], $fields = null, string $joinType = '', Query $query = null)
    {
        throw new DbException('relation not support: hasWhere');
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-17
     * Time: 17:26
     * @param array $resultSet 数据集
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 是否关联缓存
     * @return void
     */
    public function withQuerySet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $morphKey = $this->morphKey;
        $morphType = $this->morphType;
        $type = $this->type;

        $range = [];
        foreach ($resultSet as $result) {
            $pk = $result->getPk();
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $data = $this->withQueryMorphToMany([
                [$morphKey, 'in', $range],
                [$morphType, '=', $type]
            ], $subRelation, $closure, $cache);

            foreach ($resultSet as $result) {
                $pk = $result->getPk();
                if (!isset($data[$result->$pk])) {
                    $data[$result->$pk] = [];
                }

                $result->setRelation($relation, $this->resultSetBuild($data[$result->$pk], clone $this->parent));
            }
        }
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-17
     * Time: 17:27
     * @param Model $result 数据对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 是否关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $morphKey = $this->morphKey;
        $morphType = $this->morphType;
        $type = $this->type;
        $pk = $result->getPk();

        if (isset($result->$pk)) {
            $key = $result->$pk;
            $data = $this->withQueryMorphToMany([
                [$morphKey, '=', $key],
                [$morphType, '=', $type]
            ], $subRelation, $closure, $cache);

            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            $this->setRelation($relation, $this->resultSetBuild($data[$key], clone $this->parent));
        }
    }

    /**
     * Note: 多态一对多,关联模型预查询
     * Date: 2023-06-19
     * Time: 14:02
     * @param array $where 关联预查询条件
     * @param array $subRelation 子关联
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withQueryMorphToMany(array $where, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $this->query->removeOption('where');

        if ($closure) {
            $this->baseQuery = true;
            $closure($this->getClosureType($closure));
        }

        $list = $this->query
            ->where($where)
            ->with($subRelation)
            ->cache($cache[0] ?? false, $cache[1] ?? null, $cache[2] ?? null)
            ->select();

        $morphKey = $this->morphKey;
        $data = [];
        foreach ($list as $set) {
            $key = $set->$morphKey;

            if ($this->withLimit && isset($data[$key]) && count($data[$key]) >= $this->withLimit) {
                continue;
            }

            $data[$key][] = $set;
        }

        return $data;
    }

    /**
     * Note: 关联统计
     * Date: 2023-06-19
     * Time: 14:14
     * @param Model $result 数据对象
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return int
     */
    public function getRelationAggreate(Model $result, Closure $closure = null, string $aggreate = 'count', string $field = '*', string &$name = null)
    {
        $pk = $result->getPk();

        if (!isset($result->$pk)) {
            return 0;
        }

        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        return $this->query
            ->where([
                [$this->morphKey, '=', $result->$pk],
                [$this->morphType, '=', $this->type]
            ])
            ->$aggreate($field);
    }

    /**
     * Note: 创建关联统计子查询
     * Date: 2023-06-19
     * Time: 14:14
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
            ->where($this->morphKey, '=', $this->parent->getTable() . '.' . $this->parent->getPk())
            ->where($this->morphType, '=', $this->type)
            ->fetchSql()
            ->$aggreate($field);
    }

    /**
     * Note: 保存（新增）当前关联数据对象
     * Date: 2023-06-19
     * Time: 14:30
     * @param array|Model $data 数据
     * @param bool $replace 是否自动识别为更新
     * @return Model|false
     */
    public function save($data, $replace = true)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }

        $pk = $this->parent->getPk();
        $data[$this->morphKey] = $this->parent->$pk;
        $data[$this->morphType] = $this->type;

        $model = new $this->model($data);
        return $model->replace($replace)->save($data) ? $model : false;
    }

    /**
     * Note: 批量保存（新增）当前关联数据对象
     * Date: 2023-06-19
     * Time: 14:40
     * @param iterable $dataSet 数据集
     * @param bool $replace 是否自动识别更新
     * @return array|false
     */
    public function saveAll(iterable $dataSet, bool $replace = false)
    {
        $result = [];

        foreach ($dataSet as $key => $data) {
            $result[] = $this->save($data, $replace);
        }

        return empty($result) ? false : $result;
    }

    /**
     * Note: 执行基础查询(仅执行一次)
     * Date: 2023-06-17
     * Time: 17:01
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery) && $this->parent->getData()) {
            $pk = $this->parent->getPk();

            $this->query->where([
                [$this->morphKey, '=', $this->parent->$pk],
                [$this->morphType, '=', $this->type]
            ]);

            $this->baseQuery = true;
        }
    }
}