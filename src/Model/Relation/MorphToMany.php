<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model;
use Enna\Framework\Helper\Str;
use Enna\Orm\Db\Raw;
use Enna\Orm\Model\Pivot;

/**
 * 多态多对多关联
 * Class MorphToMany
 * @package Enna\Orm\Model\Relation
 */
class MorphToMany extends BelongsToMany
{
    /**
     * 多态字段名
     * @var string
     */
    protected $morphType;

    /**
     * 多态模型名
     * @var string
     */
    protected $morphClass;

    /**
     * 是否反向关联
     * @var bool
     */
    protected $inverse;

    /**
     * MorphToMany constructor.
     * @param Model $parent 父模型
     * @param string $model 关联模型名
     * @param string $middle 中间表模型名
     * @param string $morphType 多态字段:类型
     * @param string $morphKey 多态字段:外键
     * @param string $localKey 关联模型外键
     * @param bool $inverse 反向关联
     */
    public function __construct(Model $parent, string $model, string $middle, string $morphType, string $morphKey, string $localKey, bool $inverse = false)
    {
        $this->morphType = $morphType;
        $this->inverse = $inverse;

        $this->morphClass = $inverse ? Str::snake(class_basename($model)) : Str::snake(class_basename($parent));
        $foreignKey = $inverse ? $morphKey : $localKey;
        $localKey = $inverse ? $localKey : $morphKey;

        parent::__construct($parent, $model, $middle, $foreignKey, $localKey);
    }

    /**
     * Note: 预载入关联查询（数据集）
     * Date: 2023-06-20
     * Time: 17:20
     * @param array $resultSet 数据集
     * @param string $relation 当前关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure $closure 闭包
     * @param array $cache 关联缓存
     * @return void
     */
    public function withQuerySet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;
        $pk = $resultSet[0]->getPk();

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $data = $this->withQueryManyToMany([
                ['pivot.' . $localKey, 'in', $range],
                ['pivot.' . $this->morphType, '=', $this->morphClass]
            ], $subRelation, $closure, $cache);

            foreach ($resultSet as $result) {
                if (!isset($data[$result->$pk])) {
                    $data[$result->$pk] = [];
                }

                $result->setRelation($relation, $this->resultSetBuild($data[$result->$pk], clone $this->parent));
            }
        }
    }

    /**
     * Note: 预载入关联查询（数据）
     * Date: 2023-06-20
     * Time: 17:21
     * @param Model $result 模型对象
     * @param string $relation 当前关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure $closure 闭包
     * @param array $cache 关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $pk = $result->getPk();

        if (isset($result->$pk)) {
            $pk = $result->$pk;
            // 查询管理数据
            $data = $this->eagerlyManyToMany([
                ['pivot.' . $this->localKey, '=', $pk],
                ['pivot.' . $this->morphType, '=', $this->morphClass]
            ], $subRelation, $closure, $cache);

            // 关联数据封装
            if (!isset($data[$pk])) {
                $data[$pk] = [];
            }

            $result->setRelation($relation, $this->resultSetBuild($data[$pk], clone $this->parent));
        }
    }

    /**
     * Note: 多对多关联预查询
     * Date: 2023-06-20
     * Time: 17:40
     * @param array $where 关联预查询条件
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withQueryManyToMany(array $where, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $list = $this->belongsToManyQuery($this->foreignKey, $this->localKey, $where)
            ->with($subRelation)
            ->cache($cache[0] ?? false, $cache[1] ?? null, $cache[2] ?? null)
            ->select();

        $data = [];
        foreach ($list as $set) {
            $pivot = [];
            foreach ($set->getData() as $key => $val) {
                if (strpos($key, '__')) {
                    [$name, $attr] = explode('__', $key, 2);
                    if ($name == 'pivot') {
                        $pivot[$attr] = $val;
                        unset($set->$key);
                    }
                }
            }

            $key = $pivot[$this->localKey];

            if ($this->withLimit && isset($data[$key]) && count($data[$key]) >= $this->withLimit) {
                continue;
            }

            $this->setRelation($this->pivotDataName, $this->newPivot($pivot));

            $data[$key][] = $set;
        }

        return $data;
    }

    /**
     * Note: 关联统计
     * Date: 2023-06-20
     * Time: 17:46
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

        if (!isset($result[$pk])) {
            return 0;
        }

        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        $key = $result->$pk;

        return $this->belongsToManyQuery($this->foreignKey, $this->localKey, [
            ['pivot.' . $this->localKey, '=', $key],
            ['pivot.' . $this->morphType, '=', $this->morphClass]
        ])->$aggreate($field);
    }

    /**
     * Note: 创建关联统计子查询
     * Date: 2023-06-20
     * Time: 17:46
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

        return $this->belongsToManyQuery($this->foreignKey, $this->localKey, [
            ['pivot.' . $this->localKey, 'exp', new Raw('=' . $this->parent->db(false)->getTable() . '.' . $this->parent->getPk())],
            ['pivot.' . $this->morphType, '=', $this->morphClass]
        ])->fetchSql()->$aggreate($field);
    }

    /**
     * Note: 保存中间表数据
     * Date: 2023-06-20
     * Time: 15:52
     * @param mixed $data 数据
     * @param array $pivot 中间表额外数据
     * @return array|Pivot
     * @throws DbException
     */
    public function attach($data, array $pivot = [])
    {
        if (is_array($data)) {
            if (key($data) == 0) {
                $id = $data;
            } else {
                $model = new $this->model;
                $id = $model->insertGetId($data);
            }
        } elseif (is_numeric($data) || is_string($data)) {
            $id = $data;
        } elseif ($data instanceof Model) {
            $id = $data->getKey();
        }

        if (!empty($id)) {
            $pivot[$this->localKey] = $this->parent->getKey();
            $pivot[$this->morphType] = $this->morphClass;

            $ids = (array)$id;
            foreach ($ids as $id) {
                $pivot[$this->foreignKey] = $id;
                $this->pivot
                    ->replace()
                    ->exists(false)
                    ->data([])
                    ->save($pivot);

                $result[] = $this->newPivot($pivot);
            }

            if (count($result)) {
                $result = $result[0];
            }

            return $result;
        } else {
            throw new DbException('miss relation data');
        }
    }

    /**
     * Note: 判断是否存在关联数据
     * Date: 2023-06-20
     * Time: 17:53
     * @param mixed $data 数据
     * @return Pivot|false
     */
    public function attached($data)
    {
        if ($data instanceof Model) {
            $id = $data->getKey();
        } else {
            $id = $data;
        }

        $pivot = $this->pivot
            ->where($this->localKey, $this->parent->getKey())
            ->where($this->morphType, $this->morphClass)
            ->where($this->foreignKey, $id)
            ->find();

        return $pivot ?: false;
    }

    /**
     * Note: 解除关联的一个中间表数据
     * Date: 2023-06-20
     * Time: 17:54
     * @param mixed $data 数据
     * @param bool $relationDel 是否同时删除关联表数据
     * @return int
     */
    public function detach($data = null, bool $relationDel = false)
    {
        if (is_array($data)) {
            $id = $data;
        } elseif (is_numeric($data) || is_string($data)) {
            $id = $data;
        } elseif ($data instanceof Model) {
            $id = $data->getKey();
        }

        $pivot = [];
        $pivot[] = [$this->localKey, '=', $this->parent->getKey()];
        $pivot[] = [$this->foreignKey, '=', $this->morphClass];
        if (isset($id)) {
            $pivot[] = [$this->foreignKey, is_array($id) ? 'in' : '=', $id];
        }
        $result = $this->pivot->where($pivot)->delete();

        //删除关联数据
        if (isset($id) && $relationDel) {
            $model = $this->model;
            $model::destroy($id);
        }

        return $result;
    }

    /**
     * Note: 数据同步
     * Date: 2023-06-20
     * Time: 17:55
     * @param array $ids 需要同步的关联模型的ID
     * @param bool $detaching 是否删除关联
     * @return array
     */
    public function sync(array $ids, bool $detaching = true)
    {
        $changes = [
            'attached' => [],
            'detached' => [],
            'updated' => [],
        ];

        $current = $this->pivot
            ->where($this->localKey, '=', $this->parent->getKey())
            ->where($this->morphType, '=', $this->morphClass)
            ->column($this->foreignKey);

        $records = [];
        foreach ($ids as $key => $val) {
            if (!is_array($val)) {
                $records[$val] = [];
            } else {
                $records[$key] = $val;
            }
        }

        $detached = array_diff($changes, array_keys($records));
        if ($detaching && count($detached) > 0) {
            $this->detach($detached);
            $changes['detached'] = $detached;
        }

        foreach ($records as $id => $attributes) {
            if (!in_array($id, $current)) {
                $this->attach($id, $attributes);
                $changes['attached'][] = $id;
            } elseif (count($attributes) > 0 && $this->attach($id, $attributes)) {
                $changes['updated'][] = $id;
            }
        }

        return $changes;
    }

    /**
     * Note: 执行基础查询(仅执行一次)
     * Date: 2023-06-20
     * Time: 17:23
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            $foreignKey = $this->foreignKey;
            $localKey = $this->localKey;

            $this->belongsToManyQuery($foreignKey, $localKey, [
                ['pivot.' . $localKey, '=', $this->parent->getKey()],
                ['pivot.' . $this->morphType, '=', $this->morphClass]
            ]);

            $this->baseQuery = true;
        }
    }

    /**
     * Note: 关联查询
     * Date: 2023-06-20
     * Time: 17:26
     * @param string $foreignKey 关联模型关联键
     * @param string $localKey 当前模型关联键
     * @param array $condition 关联查询条件
     * @return Query
     */
    protected function belongsToManyQuery(string $foreignKey, string $localKey, array $condition = [])
    {
        if (empty($this->baseQuery)) {


            $tableName = $this->query->getTable();
            $table = $this->pivot->db()->getTable();

            if ($this->withoutField) {
                $this->query->withoutField($this->withoutField);
            }
            if ($this->withLimit) {
                $this->query->limit($this->withLimit);
            }

            $fields = $this->getQueryFields($tableName);

            $this->query
                ->field($fields)
                ->tableField(true, $table, 'pivot', 'pivot__')
                ->join([$table => 'pivot'], 'pivot.' . $foreignKey . '=' . $tableName . '.' . $this->query->getPk())
                ->where($condition);
        }

        return $this->query;
    }
}