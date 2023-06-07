<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Model;
use Closure;
use Enna\Orm\Model\Collection as ModelCollection;
use Enna\Framework\Helper\Str;

/**
 * 模型及关联查询
 * Trait ModelRelationQuery
 * @package Enna\Orm\Db\Concern
 */
trait ModelRelationQuery
{
    /**
     * 当前模型对象
     * @var Model
     */
    protected $model;

    /**
     * Note: 指定模型
     * Date: 2023-03-31
     * Time: 14:36
     * @param Model $model 模型对象实例
     * @return $this
     */
    public function model(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Note: 获取当前模型对象实例
     * Date: 2023-03-31
     * Time: 14:36
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Note: 设置需要隐藏的的属性
     * Date: 2023-05-24
     * Time: 9:20
     * @param array $hidden 属性名
     * @return $this
     */
    public function hidden(array $hidden)
    {
        $this->options['hidden'] = $hidden;

        return $this;
    }

    /**
     * Note: 设置需要输出的属性
     * Date: 2023-05-24
     * Time: 10:09
     * @param array $visible 属性名
     * @return $this
     */
    public function visible(array $visible)
    {
        $this->options['visible'] = $visible;
    }

    /**
     * Note: 设置需要追加的属性
     * Date: 2023-05-24
     * Time: 10:10
     * @param array $append 属性
     * @return $this
     */
    public function append(array $append)
    {
        $this->options['append'] = $append;

        return $this;
    }

    /**
     * Note: 添加查询范围
     * Date: 2023-05-12
     * Time: 18:25
     * @param array|string|Closure $scope 查询范围定义
     * @param array ...$args
     * @return $this
     */
    public function scope($scope, ...$args)
    {
        array_unshift($args, $this);

        if ($scope instanceof Closure) {
            call_user_func_array($scope, $args);
            return $this;
        }

        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }

        if ($this->model) {
            foreach ($scope as $name) {
                $method = 'scope' . trim($name);

                if (method_exists($this, $method)) {
                    call_user_func_array([$this, $method], $args);
                }
            }
        }

        return $this;
    }

    /**
     * Note: 设置关联查询
     * Date: 2023-05-24
     * Time: 10:45
     * @param array $relation 关联名称
     * @return $this
     */
    public function relation(array $relation)
    {
        if (!empty($relation)) {
            $this->options['relation'] = $relation;
        }

        return $this;
    }

    /**
     * Note: 搜索器:预定义查询条件
     * Date: 2023-05-23
     * Time: 15:37
     * @param array|string $fields 搜索字段
     * @param mixed $data 搜索数据
     * @param string $prefix 字段前缀标识
     * @return $this
     */
    public function withSearch($fields, $data = [], string $prefix = '')
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        foreach ($fields as $key => $field) {
            if ($fields instanceof Closure) {
                $fields($this, $data[$key] ?? null, $data, $prefix);
            } elseif ($this->model) {
                $fileName = is_numeric($key) ? $fileName : $key;

                $method = 'search' . Str::studly($fileName) . 'Attr';

                if (method_exists($this->model, $method)) {
                    $this->model->$method($this, $data[$field] ?? null, $data, $prefix);
                } elseif (isset($data[$field])) {
                    $this->where($fileName, '=', $data[$field]);
                }
            }
        }

        return $this;
    }

    /**
     * Note: 获取器:设置数据字段
     * Date: 2023-05-20
     * Time: 10:15
     * @param array|string $name 字段名
     * @param callable $callback 闭包获取器
     * @return $this
     */
    public function withAttr($name, callable $callback = null)
    {
        if (is_array($name)) {
            $this->options['with_attr'] = $name;
        } else {
            $this->options['with_attr'][$name] = $callback;
        }

        return $this;
    }

    /**
     * Note: 关联预载入:in方式
     * Date: 2023-05-23
     * Time: 15:32
     * @param array|string $with 关联方法名
     * @return $this
     */
    public function with($with)
    {
        if (!empty($with)) {
            $this->options['with'] = (array)$with;
        }

        return $this;
    }

    /**
     * Note: 关联预载入:join方式
     * Date: 2023-05-24
     * Time: 10:47
     * @param array|string $with 关联方法名
     * @param string $joinType join类型
     * @return $this
     */
    public function withJoin($with, $joinType = '')
    {
        if (empty($with)) {
            return $this;
        }

        $with = (array)$with;
        $first = true; //第一次循环时

        foreach ($with as $key => $relation) {
            $closure = null;
            $field = true;

            if ($relation instanceof Closure) {
                $closure = $relation;
                $relation = $key;
            } elseif (is_array($relation)) {
                $field = $relation;
                $relation = $key;
            } elseif (is_string($relation) && strpos($relation, '.')) {
                $relation = strstr($relation, '.', true);
            }

            $result = $this->model->withJoin($this, $relation, $field, $joinType, $closure, $first);

            if (!$result) {
                unset($with[$key]);
            } else {
                $first = false; //后面循环时
            }
        }

        $this->options['with_join'] = $with;

        return $this;
    }

    /**
     * Note: 关联缓存
     * Date: 2023-05-26
     * Time: 14:36
     * @param int|array|bool $relation 关联方法名
     * @param mixed $key 缓存key
     * @param int|\DateTime $expire 缓存有效期
     * @param string $tag 缓存标签
     * @return $this
     */
    public function withCache($relation = true, $key = true, $expire = null, string $tag = null)
    {
        if ($relation === false || $key === false || !$this->getConnection()->getCache()) {
            return $this;
        }

        if ($key instanceof \DateTimeInterface || $key instanceof \DateInterval || (is_int($key) && is_null($expire))) {
            $expire = $key;
            $key = true;
        }

        if ($relation === true || is_numeric($relation)) {
            $this->options['with_cache'] = $relation;
            return $this;
        }

        $relations = (array)$relation;
        foreach ($relations as $name => $relation) {
            if (!is_numeric($name)) {
                $this->options['with_cache'][$name] = is_array($relation) ? $relation : [$key, $expire, $tag];
            } else {
                $this->options['with_cache'][$relation] = [$key, $expire, $tag];
            }
        }

        return $this;
    }

    /**
     * Note: 关联统计
     * Date: 2023-05-25
     * Time: 18:33
     * @param string|array $relations 关联方法名
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param bool $subQuery 是否子查询
     * @return $this
     */
    protected function withAggreate($relations, string $aggreate = 'count', $field = '*', bool $subQuery = true)
    {
        if (!$subQuery) {
            $this->options['with_count'][] = [$relations, $aggreate, $field];
        } else {
            if (!isset($this->options['field'])) {
                $this->field('*');
            }

            $this->model->relationAggreate($this, (array)$relations, $aggreate, $field, true);
        }

        return $this;
    }

    /**
     * Note: 关联统计count
     * Date: 2023-05-26
     * Time: 10:02
     * @param string|array $relation 关联方法名
     * @param bool $subQuery 是否使用子查询
     * @return $this
     */
    public function withCount($relation, bool $subQuery = true)
    {
        return $this->withAggreate($relation, 'count', '*', $subQuery);
    }

    /**
     * Note: 关联统计sum
     * Date: 2023-05-26
     * Time: 10:31
     * @param string|array $relation 关联方法名
     * @param string $field 字段
     * @param bool $subQuery 是否使用子查询
     * @return $this
     */
    public function withSum($relation, string $field, bool $subQuery = true)
    {
        return $this->withAggreate($relation, 'sum', $field, $subQuery);
    }

    /**
     * Note: 关联统计max
     * Date: 2023-05-26
     * Time: 10:31
     * @param string|array $relation 关联方法名
     * @param string $field 字段
     * @param bool $subQuery 是否使用子查询
     * @return $this
     */
    public function withMax($relation, string $field, bool $subQuery = true)
    {
        return $this->withAggreate($relation, 'max', $field, $subQuery);
    }

    /**
     * Note: 关联统计min
     * Date: 2023-05-26
     * Time: 10:31
     * @param string|array $relation 关联方法名
     * @param string $field 字段
     * @param bool $subQuery 是否使用子查询
     * @return $this
     */
    public function withMin($relation, string $field, bool $subQuery = true)
    {
        return $this->withAggreate($relation, 'min', $field, $subQuery);
    }

    /**
     * Note: 关联统计avg
     * Date: 2023-05-26
     * Time: 10:31
     * @param string|array $relation 关联方法名
     * @param string $field 字段
     * @param bool $subQuery 是否使用子查询
     * @return $this
     */
    public function withAvg($relation, string $field, bool $subQuery = true)
    {
        return $this->withAggreate($relation, 'avg', $field, $subQuery);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-26
     * Time: 10:37
     * @param string $relation 关联方法名
     * @param string $operator 比较操作符
     * @param int $count 个数
     * @param string $id 关联表的统计字段
     * @param string $joinType JOIN类型
     * @return $this
     */
    public function has(string $relation, $operator = '>=', int $count = 1, string $id = '*', string $joinType = '')
    {
        return $this->model->has($relation, $operator, $count, $id, $joinType, $this);
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-05-26
     * Time: 10:41
     * @param string $relation 关联方法名
     * @param mixed $where 查询条件(支持比较)
     * @param string $fields 字段
     * @param string $joinType JOIN类型
     * @return $this
     */
    public function hasWhere(string $relation, $where = [], string $fields = '*', string $joinType = '')
    {
        return $this->model->hasWhere($relation, $where, $fields, $joinType, $this);
    }

    /**
     * Note: 查询数据转换为模型数据集对象
     * Date: 2023-05-23
     * Time: 14:57
     * @param array $resultSet 数据集
     * @return ModelCollection
     */
    protected function resultSetToModelCollection(array $resultSet)
    {
        if (empty($resultSet)) {
            return $this->model->toCollection();
        }

        //检查动态获取器:是否有关联模型|是否有JSON字段
        if (!empty($this->options['with_attr'])) {
            foreach ($this->options['with_attr'] as $name => $val) {
                if (strpos($name, '.')) {
                    [$relation, $field] = explode('.', $name);

                    $withRelationAttr[$relation][$field] = $val;
                    unset($this->options['with_attr'][$name]);
                }
            }
        }
        $withRelationAttr = $withRelationAttr ?? [];

        //转换为模型数据集对象
        foreach ($resultSet as $key => &$result) {
            $this->resultToModel($result, $this->options, true, $withRelationAttr);
        }

        //预载入查询
        if (!empty($this->options['with'])) {
            $result->withQuerySet($resultSet, $this->options['with'], $withRelationAttr, false, $this->options['with_cache'] ?? false);
        }

        //join预载入查询
        if (!empty($this->options['with_join'])) {
            $result->withQuerySet($resultSet, $option['with_join'], $withRelationAttr, true, $this->options['with_cache'] ?? false);
        }

        return $this->model->toCollection($resultSet);
    }

    /**
     * Note: 查询数据转换为模型对象
     * Date: 2023-05-19
     * Time: 16:45
     * @param array $result 查询数据
     * @param array $options 查询参数
     * @param bool $resultSet 是否为数据集查询
     * @param array $withRelationAttr 关联字段获取器
     * @return void
     */
    protected function resultToModel(array &$result, array $options = [], bool $resultSet = false, array $withRelationAttr = [])
    {
        //检查动态获取器:是否有关联模型|是否有JSON字段
        if (!empty($options['with_attr']) && empty($withRelationAttr)) {
            foreach ($options['with_attr'] as $name => $val) {
                if (strpos($name, '.')) {
                    [$relation, $field] = explode('.', $name);

                    $withRelationAttr[$relation][$field] = $val;
                    unset($options['with_attr'][$name]);
                }
            }
        }

        //json数据处理
        if (!empty($options['json'])) {
            $this->jsonResult($result, $options['json'], $options['json_assoc'], $withRelationAttr);
        }

        //将结果实例化为Model
        $result = $this->model->newInstance($result, $resultSet ? null : $this->getModelUpdateCondition($options));

        //动态获取器
        if (!empty($options['with_attr'])) {
            $result->withAttribute($options['with_attr']);
        }

        //输出属性控制
        if (!empty($options['visible'])) {
            $result->visible($options['visible']);
        }
        if (!empty($options['hidden'])) {
            $result->hidden($options['hidden']);
        }
        if (!empty($options['append'])) {
            $result->append($options['append']);
        }

        //关联查询
        if (!empty($options['relation'])) {
            $result->relationQuery($options['relation'], $withRelationAttr);
        }

        //预载入关联查询
        if (!$resultSet && !empty($options['with'])) {
            $result->withQuery($result, $options['with'], $withRelationAttr, false, $options['with_cache'] ?? false);
        }

        //join预载入查询
        if (!$resultSet && !empty($options['with_join'])) {
            $result->withQuery($result, $options['with_join'], $withRelationAttr, true, $options['with_cache'] ?? false);
        }

        //关联统计
        if (!empty($options['with_count'])) {
            foreach ($options['with_count'] as $val) {
                $result->relationAggreate($this, (array)$val[0], $val[1], $val[2], false);
            }
        }
    }
}