<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

use Enna\Framework\Helper\Str;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Enna\Orm\Model\Relation\HasOne;
use Enna\Orm\Model\Relation\HasMany;
use Closure;
use Enna\Orm\Model\Relation\OneToOne;
use Enna\Orm\Model\Relation\BelongsTo;
use Enna\Orm\Db\Exception\DbException;

trait RelationShip
{
    /**
     * 父关联模型对象
     * @var Model
     */
    private $parent;

    /**
     * 模型关联数据
     * @var array
     */
    private $relation = [];

    /**
     * 关联写入定义信息
     * @var array
     */
    private $together = [];

    /**
     * 关联自动写入信息
     * @var array
     */
    protected $relationWrite = [];

    /**
     * Note: 设置父关联模型对象
     * Date: 2023-05-25
     * Time: 10:24
     * @param Model $model 模型
     * @return $this
     */
    public function setParent(Model $model)
    {
        $this->parent = $model;

        return $this;
    }

    /**
     * Note: 获取父关联模型对象
     * Date: 2023-05-25
     * Time: 10:25
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Note: 获取当前模型的关联模型数据
     * Date: 2023-05-22
     * Time: 10:58
     * @param string|null $name 关联方法名
     * @param bool $auto 不存在是否自动获取
     * @return mixed
     */
    public function getRelation(string $name = null, bool $auto = false)
    {
        if (is_null($name)) {
            return $this->relation;
        }

        if (array_key_exists($name, $this->relation)) {
            return $this->relation[$name];
        } elseif ($auto) {
            $relation = Str::camel($name);
            return $this->getRelationValue($relation);
        }
    }

    /**
     * Note: 设置当前模型的关联模型数据
     * Date: 2023-06-07
     * Time: 14:40
     * @param string $name 关联方法名(属性名)
     * @param mixed $value 模型数据(属性值)
     * @param array $data 数据
     * @return $this
     */
    public function setRelation(string $name, $value, array $data = [])
    {
        $method = 'set' . Str::studly($name) . 'Attr';

        if (method_exists($this, $method)) {
            $value = $this->$method($value, array_merge($this->data, $data));
        }

        $this->relation[$name] = $value;

        return $this;
    }

    /**
     * Note: 查询当前模型的关联数据
     * Date: 2023-05-26
     * Time: 17:02
     * @param array $relations 关联方法名
     * @param array $withRelationAttr 关联获取器
     * @return void
     */
    public function relationQuery(array $relations, array $withRelationAttr = [])
    {
        foreach ($relations as $key => $relation) {
            $subRelation = [];
            $closure = null;

            if ($relation instanceof Closure) {
                $closure = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $relation;
                $relation = $key;
            } elseif (strpos($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);
            }

            $method = Str::camel($relation);
            $resultResult = $this->$method();

            if (isset($withRelationAttr[$relation])) {
                $resultResult->withAttr($withRelationAttr[$relation]);
            }

            $this->relation[$relation] = $resultResult->getRelation((array)$subRelation, $closure);
        }
    }

    /**
     * Note: 一对一关联
     * Date: 2023-05-22
     * Time: 17:49
     * @param string $model 关联模型类名
     * @param string $foreignKey 外键:默认为当前模型名+_id
     * @param string $localKey 主键:当前模型主键
     * @return HasOne
     */
    public function hasOne(string $model, string $foreignKey = '', string $localKey = '')
    {
        $model = $this->parseModel($model);
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->name);
        $localKey = $localKey ?: $this->getPk();

        return new HasOne($this, $model, $foreignKey, $localKey);
    }

    /**
     * Note: belongsTo:从属关联
     * Date: 2023-05-24
     * Time: 18:04
     * @param string $model 从属模型类名
     * @param string $foreignKey 外键:从属模型名+_id
     * @param string $localKey 主键:从属模型主键
     * @return BelongsTo
     */
    public function belongsTo(string $model, string $foreignKey = '', string $localKey = '')
    {
        $model = $this->parseModel($model);
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->name);
        $localKey = $localKey ?: $this->getPk();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $relation = Str::snake($trace[1]['function']);

        return new BelongsTo($this, $model, $foreignKey, $localKey, $relation);
    }

    /**
     * Note: 一对多关联
     * Date: 2023-05-25
     * Time: 10:58
     * @param string $model 关联模型类名
     * @param string $foreignKey 外键:默认为当前模型名+_id
     * @param string $localKey 主键:当前模型主键
     * @return HasMany
     */
    public function hasMany(string $model, string $foreignKey = '', string $localKey = '')
    {
        $model = $this->parseModel($model);
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->name);
        $localKey = $localKey ?: $this->getPk();

        return new HasMany($this, $model, $foreignKey, $localKey);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-25
     * Time: 11:11
     * @param string $relation 关联方法名
     * @param string $operator 比较操作符
     * @param int $count 数量
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query|null $query 查询对象
     * @return Query
     */
    public static function has(string $relation, string $operator = '>=', int $count = 1, string $id = '*', string $joinType = '', Query $query = null)
    {
        return (new static())->$relation()->has($operator, $count, $id, $joinType, $query);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-22
     * Time: 18:29
     * @param string $relation 关联方法名
     * @param array|Closure $where 查询条件(数组或闭包)
     * @param string $field 字段
     * @param string $joinType JOIN类型
     * @param Query|null $query Query对象
     * @return Query
     */
    public static function hasWhere(string $relation, $where = [], string $fields = '*', string $joinType = '', Query $query = null)
    {
        return (new static())->$relation()->hasWhere($where, $fields, $joinType, $query);
    }

    /**
     * Note: 检查属性是否为关联属性
     * Date: 2023-05-16
     * Time: 14:18
     * @param string $attr 属性名
     * @return string|false
     */
    protected function isRelationAttr(string $attr)
    {
        $relation = Str::camel($attr);

        if ((method_exists($this, $relation) && !method_exists('Enna\Orm\Model', $relation)) || isset(static::$macro[static::class][$relation])) {
            return $relation;
        }

        return false;
    }

    /**
     * Note: 预载入关联查询:JOIN方式
     * Date: 2023-05-24
     * Time: 14:10
     * @param Query $query 查询对象
     * @param string $relation 关联方法名
     * @param mixed $field 字段
     * @param string $joinType JOIN类型
     * @param Closure $closure 闭包
     * @param bool $first 是否第一次关联
     * @return bool
     */
    public function withJoin(Query $query, string $relation, $field, string $joinType = '', Closure $closure = null, bool $first = false)
    {
        $relation = Str::camel($relation);
        $class = $this->$relation();

        if ($class instanceof OneToOne) {
            $class->withJoin($query, $relation, $field, $joinType, $closure, $first);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Note: 预载入关联查询:返回模型对象
     * Date: 2023-06-02
     * Time: 18:38
     * @param array $resultSet 模型对象
     * @param array $relations 关联
     * @param array $withRelationAttr 关联获取器
     * @param bool $join 是否JOIN方式
     * @param false $cache 关联缓存
     * @return void
     */
    public function withQuerySet(array &$resultSet, array $relations, array $withRelationAttr = [], bool $join = false, $cache = false)
    {
        foreach ($relations as $key => $relation) {
            $subRelation = [];
            $closure = null;

            if ($relation instanceof Closure) {
                $closure = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $resultSet;
                $relation = $key;
            } elseif (strpos($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);

                $subRelation = [$subRelation];
            }

            $method = Str::camel($relation);
            $resultResult = $this->$method();

            if (isset($withRelationAttr[$relation])) {
                $resultResult->withAttr($withRelationAttr[$relation]);
            }

            if (is_scalar($cache)) {
                $relationCache = [$cache];
            } else {
                $relationCache = $cache[$relation] ?? [];
            }

            $relationResult->withQuerySet($resultSet, $relation, $subRelation, $closure, $relationCache, $join);
        }
    }

    /**
     * Note: 预载入关联查询:返回模型对象
     * Date: 2023-06-02
     * Time: 18:02
     * @param Model $result 模型对象
     * @param array $relations 关联
     * @param array $withRelationAttr 关联获取器
     * @param bool $join 是否JOIN方式
     * @param bool $cache 关联缓存
     * @return void
     */
    public function withQuery(Model $result, array $relations, array $withRelationAttr = [], bool $join = false, bool $cache = false)
    {
        foreach ($relations as $relation) {
            $subRelation = [];
            $closure = null;

            if ($relation instanceof Closure) {
                $closure = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $relation;
                $relation = $key;
            } elseif (strpos($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);

                $subRelation = [$subRelation];
            }

            $method = Str::camel($relation);
            $resultResult = $this->$method();

            if (isset($withRelationAttr[$relation])) {
                $resultResult->withAttr($withRelationAttr[$relation]);
            }

            if (is_scalar($cache)) {
                $relationCache = [$cache];
            } else {
                $relationCache = $cache[$relation] ?? [];
            }

            $resultResult->withQuery($result, $relation, $subRelation, $closure, $relationCache, $join);
        }
    }

    /**
     * Note: 绑定(一对一)关联属性到当前模型
     * Date: 2023-05-25
     * Time: 10:09
     * @param string $relation 关联名称
     * @param array $attrs 绑定属性
     * @return $this
     * @throws DbException
     */
    public function bindAttr(string $relation, array $attrs = [])
    {
        $relation = $this->getRelation($relation);

        foreach ($attrs as $key => $attr) {
            $key = is_numeric($key) ? $attr : $key;
            $value = $this->getOrigin($key);

            if (!is_null($value)) {
                throw new DbException('bind attr has exists:' . $key);
            }

            $this->set($key, $relation ? $relation->$attr : null);
        }

        return $this;
    }

    /**
     * Note: 关联数据写入
     * Date: 2023-05-25
     * Time: 10:21
     * @param array $relation 关联模型名
     * @return $this
     */
    public function together(array $relation)
    {
        $this->together = $relation;

        $this->checkAutoRelationWrite();

        return $this;
    }

    /**
     * Note: 关联统计
     * Date: 2023-05-26
     * Time: 9:58
     * @param Query $query 查询对象
     * @param array $relations 关联名
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param bool $useSubQuery 子查询
     * @return void
     */
    public function relationAggreate(Query $query, array $relations, string $aggreate = 'sum', string $field = '*', bool $useSubQuery = true)
    {
        foreach ($relations as $key => $relation) {
            $closure = null;
            $name = null;

            if ($relation instanceof Closure) {
                $closure = $relation;
                $relation = $key;
            } elseif (is_array($relation)) {
                $name = $relation;
                $relation = $key;
            }

            $relation = Str::camel($relation);

            if ($useSubQuery) {
                $count = $this->$relation()->getRelationAggreateQuery($closure, $aggreate, $field, $name);
            } else {
                $count = $this->$relation()->getRelationAggreate($this, $closure, $aggreate, $field, $name);
            }

            if (empty($name)) {
                $name = Str::snake($relation) . '_' . $aggreate;
            }

            if ($useSubQuery) {
                $query->field(['(' . $count . ')' => $name]);
            } else {
                $this->setAttr($name, $count);
            }
        }
    }

    /**
     * Note: 自动关联数据的写入检查
     * Date: 2023-05-25
     * Time: 10:33
     * @return void
     */
    protected function checkAutoRelationWrite()
    {
        foreach ($this->together as $key => $name) {
            if (is_array($name)) {
                $this->relationWrite[$key] = [];
                foreach ($name as $val) {
                    if (isset($this->data[$val])) {
                        $this->relationWrite[$key][$val] = $this->data[$val];
                    }
                }
            } elseif (isset($this->relation[$name])) {
                $this->relationWrite[$name] = $this->relation[$name];
            } elseif (isset($this->data[$name])) {
                $this->relationWrite[$name] = $this->data[$name];
                unset($this->data[$name]);
            }
        }
    }

    /**
     * Note: 解析模型获取模型的完整命名空间
     * Date: 2023-05-22
     * Time: 18:02
     * @param string $model 模型名或包含命名空间的模型名
     * @return string
     */
    protected function parseModel(string $model)
    {
        if (strpos($model, '\\') === false) {
            $path = explode('\\', static::class);
            array_pop($path);
            array_push($path, Str::studly($model));
            $model = implode('\\', $path);
        }

        return $model;
    }

    /**
     * Note: 获取模型的默认外键名
     * Date: 2023-05-22
     * Time: 18:15
     * @param string $name 模型名
     * @return string
     */
    protected function getForeignKey(string $name)
    {
        if (strpos($name, '\\')) {
            $class = is_object($name) ? get_class($name) : $class;
            $name = basename(str_replace('\\', '/', $class));
        }

        return Str::snake($name) . '_id';
    }

    /**
     * Note: 获取关联模型数据
     * Date: 2023-06-07
     * Time: 10:54
     * @param Relation $modelRelation 关联模型对象
     * @return mixed
     */
    protected function getRelationData(Relation $modelRelation)
    {
        if ($this->parent && !$modelRelation->isSelfRelation() && get_class($this->parent) == get_class($modelRelation->getModel())) {
            return $this->parent;
        }

        return $modelRelation->getRealtion();
    }
}