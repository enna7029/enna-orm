<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Framework\Helper\Collection;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Raw;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Enna\Orm\Model\Pivot;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Paginator;


class BelongsToMany extends Relation
{
    /**
     * 中间表名 如:Access
     * @var string
     */
    protected $middle;

    /**
     * 中间表模型名 如:app\model\Access
     * @var string
     */
    protected $pivotName;

    /**
     * 中间表模型对象
     * @var Pivot
     */
    protected $pivot;

    /**
     * 中间表数据名称
     * @var string
     */
    protected $pivotDataName = 'pivot';

    /**
     * BelongsToMany constructor.
     * @param Model $parent 父模型 如:user
     * @param string $model 当前模型 如:role
     * @param string $middle 中间模型 如:access
     * @param string $foreignKey 当前模型外键 如:user_id
     * @param string $localKey 当前模型关联键 如:role_id
     */
    public function __construct(Model $parent, string $model, string $middle, string $foreignKey, string $localKey)
    {
        $this->parent = $parent;
        $this->model = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        if (strpos($middle, '\\') !== false) {
            $this->middle = class_basename($middle);
            $this->pivotName = $middle;
        } else {
            $this->middle = $middle;
        }

        $this->query = (new $model)->db();
        $this->pivot = $this->newPivot();
    }

    /**
     * Note: 实例化中间表模型
     * Date: 2023-06-14
     * Time: 18:21
     * @param array $data 数据
     * @return Pivot
     * @throws DbException
     */
    protected function newPivot(array $data = [])
    {
        $class = $this->pivotName ?: Pivot::class;
        $pivot = new $class($data, $this->parent, $this->middle);

        if ($pivot instanceof Pivot) {
            return $pivot;
        } else {
            throw new DbException('pivot model must extends: \Enna\Orm\Model\Pivot');
        }
    }

    /**
     * Note: 设置中间表模型
     * Date: 2023-06-15
     * Time: 15:54
     * @param string $pivot
     * @return $this
     */
    public function pivot(string $pivot)
    {
        $this->pivotName = $pivot;

        return $this;
    }

    /**
     * Note: 设置中间表数据名称
     * Date: 2023-06-15
     * Time: 15:55
     * @param string $name
     * @return $this
     */
    public function name(string $name)
    {
        $this->pivotDataName = $name;

        return $this;
    }

    /**
     * Note: 延迟获取关联数据
     * Date: 2023-06-16
     * Time: 9:42
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 闭包
     * @return Collection
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $result = $this->relation($subRelation)
            ->select()
            ->setParent(clone $this->parent);

        foreach ($result as $model) {
            $this->hybridPivot($model);
        }

        return $this;
    }

    /**
     * Note: 合成中间表模型
     * Date: 2023-06-16
     * Time: 9:45
     * @param Model $models 模型对象
     * @return array
     */
    protected function hybridPivot(Model $model)
    {
        $pivot = [];
        foreach ($model->getData() as $key => $val) {
            if (strpos($key, '__')) {
                [$name, $attr] = explode('__', $key, 2);

                if ($name == 'pivot') {
                    $pivot[$attr] = $vale;
                    unset($model->$key);
                }
            }
        }

        $pivotData = $this->pivot->newInstance($pivot, [
            [$this->localKey, '=', $this->parent->getKey(), null],
            [$this->foreignKey, '=', $result->getKey(), null],
        ]);

        $model->setRelation($this->pivotDataName, $pivotData);

        return $pivot;
    }

    /**
     * Note: 重载select方法
     * Date: 2023-06-16
     * Time: 10:32
     * @param mixed $data 数据
     * @return Collection
     */
    public function select($data = null)
    {
        $this->baseQuery();
        $result = $this->query->select($data);
        $this->hybridPivot($result);

        return $result;
    }

    /**
     * Note: 重载paginate方法
     * Date: 2023-06-16
     * Time: 13:50
     * @param int|array $listRow 每页数量
     * @param bool $simple 是否简单分页
     * return Paginator
     */
    public function paginate($listRow = null, $simple = false)
    {
        $this->baseQuery();
        $result = $this->query->paginate($listRow, $simple);
        $this->hybridPivot($result);

        return $result;
    }

    /**
     * Note: 重载find方法
     * Date: 2023-06-16
     * Time: 13:53
     * @param mixed $data 数据
     * @return Model
     */
    public function find($data = null)
    {
        $this->baseQuery();
        $result = $this->query->find($data);
        if ($result && !$result->isEmpty()) {
            $this->hybridPivot($result);
        }

        return $result;
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-16
     * Time: 14:03
     * @param string $operator 比较操作符
     * @param int $count 数量
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query $query Query查询对象
     * @return Model
     */
    public function has(string $operator = '>=', int $count = 1, string $id = '*', string $joinType = 'INNER', Query $query = null)
    {
        return $this->parent;
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-16
     * Time: 14:04
     * @param array|Closure $where 条件
     * @param string $fields 字段
     * @param string $joinType Join类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public function hasWhere($where = [], $fields = null, string $joinType = '', Query $query = null)
    {
        throw new DbException('relation not support: hasWhere');
    }

    /**
     * Note: 设置中间表的查询条件
     * Date: 2023-06-16
     * Time: 14:10
     * @param string $field 字段
     * @param string $op 查询操作符
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function wherePivot($field, $op = null, $condition = null)
    {
        $this->query->where('pivot.' . $field, $op, $condition);

        return $this;
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-16
     * Time: 14:12
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
        $pk = $resultSet[0]->getPk();

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $data = $this->withQueryManyToMany([
                ['pivot.' . $localKey, 'in', $range]
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
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-16
     * Time: 14:13
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
        $pk = $result->getPk();

        if (isset($result->$pk)) {
            $key = $result->$pk;
            $data = $this->withQueryManyToMany([
                ['pivot.' . $localKey, '=', $key]
            ], $subRelation, $closure, $cache);

            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            $this->setRelation($relation, $this->resultSetBuild($data[$key], clone $this->parent));
        }
    }

    /**
     * Note: 多对多关联预查询
     * Date: 2023-06-16
     * Time: 14:30
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
            $pivot = $this->hybridPivot($set);
            $key = $pivot[$this->localKey];

            if ($this->withLimit && isset($data[$key]) && count($data[$key]) >= $this->withLimit) {
                continue;
            }

            $data[$key][] = $set;
        }

        return $data;
    }

    /**
     * Note: 关联统计
     * Date: 2023-06-16
     * Time: 15:10
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

        $key = $result->$pk;

        return $this->belongsToManyQuery($this->foreignKey, $this->localKey, [
            ['pivot.' . $this->localKey, '=', $key]
        ])->$aggreate($field);
    }

    /**
     * Note: 创建关联统计子查询
     * Date: 2023-06-16
     * Time: 15:10
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
            ['pivot.' . $this->localKey, 'exp', new Raw('=' . $this->parent->db(false)->getTable() . '.' . $this->parent->getPk())]
        ])->fetchSql()->$aggreate($field);
    }

    /**
     * Note: 保存(新增)当前关联数据对象
     * Date: 2023-06-16
     * Time: 15:47
     * @param mixed $data 数据
     * @param array $pivot 中间表额外数据
     * @return array|Pivot
     */
    public function save($data, array $pivot = [])
    {
        return $this->attach($data, $pivot);
    }

    /**
     * Note: 批量保存当前关联数据对象
     * Date: 2023-06-16
     * Time: 16:42
     * @param iterable $dataSet 数据集合
     * @param array $pivot 中间表额外数据
     * @param bool $samePivot 额外数据是否相同
     * @return array|false
     */
    public function saveAll(iterable $dataSet, array $pivot = [], bool $samePivot = false)
    {
        $result = [];
        foreach ($dataSet as $key => $data) {
            if (!$samePivot) {
                $pivotData = $pivot[$key] ?? [];
            } else {
                $pivotData = $pivot;
            }

            $result[] = $this->attach($data, $pivotData);
        }

        return empty($result) ? false : $result;
    }

    /**
     * Note: 保存中间表数据
     * Date: 2023-06-16
     * Time: 16:29
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

            $ids = (array)$id;
            foreach ($ids as $id) {
                $pivot[$this->foreignKey] = $id;
                $this->pivot->replace()
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
     * Date: 2023-06-16
     * Time: 16:54
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
            ->where($this->foreignKey, $id)
            ->find();

        return $pivot ?: false;
    }

    /**
     * Note: 解除关联的一个中间表数据
     * Date: 2023-06-16
     * Time: 16:58
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
     * Date: 2023-06-16
     * Time: 17:32
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
     * Note: 关联查询
     * Date: 2023-06-16
     * Time: 11:15
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


    /**
     * Note: 执行一次基础查询(仅执行一次)
     * Date: 2023-06-16
     * Time: 10:33
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            $foreignKey = $this->foreignKey;
            $localKey = $this->localKey;

            if ($this->parent->getKey() === null) {
                $condition = ['pivot.' . $localKey, 'exp', new Raw('=' . $this->parent->getTable() . '.' . $this->parent->getPk())];
            } else {
                $condition = ['pivot.' . $localKey, '=', $this->parent->getKey()];
            }

            $this->belongsToManyQuery($foreignKey, $localKey, [$condition]);

            $this->baseQuery = true;
        }
    }
}