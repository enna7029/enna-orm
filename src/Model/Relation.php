<?php
declare (strict_types=1);

namespace Enna\Orm\Model;

use Enna\Orm\Model;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;

/**
 * 模型关联基础类
 * Class Relation
 * @package Enna\Orm\Model
 */
abstract class Relation
{
    /**
     * 父模型对象
     * @var Model
     */
    protected $parent;

    /**
     * 当前关联的模型类名
     * @var string
     */
    protected $model;

    /**
     * 关联模型查询对象
     * @var Query
     */
    protected $query;

    /**
     * 关联表外键
     * @var string
     */
    protected $foreignKey;

    /**
     * 关联表主键
     * @var string
     */
    protected $localKey;

    /**
     * 是否执行关联基础查询
     * @var bool
     */
    protected $baseQuery;

    /**
     * 是否为自关联
     * @var bool
     */
    protected $selfRelation = false;

    /**
     * 关联数据数量限制
     * @var int
     */
    protected $withLimit;

    /**
     * 关联数据字段限制
     * @var array
     */
    protected $withField;

    /**
     * 排除关联数据字段
     * @var array
     */
    protected $withoutField;

    /**
     * Note: 获取关联的所属模型
     * Date: 2023-06-07
     * Time: 18:04
     * @return Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Note: 获取当前的关联模型类的Query实例
     * Date: 2023-06-07
     * Time: 18:06
     * @return Query
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Note: 获取关联表外键
     * Date: 2023-06-07
     * Time: 18:07
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Note: 获取关联表主键
     * Date: 2023-06-07
     * Time: 18:08
     * @return string
     */
    public function getLocalKey()
    {
        return $this->localKey;
    }

    /**
     * Note: 获取当前的关联模型类的实例
     * Date: 2023-06-07
     * Time: 18:09
     * @return Model
     */
    public function getModel()
    {
        return $this->query->getModel();
    }

    /**
     * Note: 当前关联是否为自关联
     * Date: 2023-06-07
     * Time: 18:10
     * @return bool
     */
    public function isSelfRelation()
    {
        return $this->selfRelation;
    }

    /**
     * Note: 限制关联模型的数量
     * Date: 2023-05-24
     * Time: 17:48
     * @param int $limit 数量
     * @return $this
     */
    public function withLimit(int $limit)
    {
        $this->withLimit = $limit;

        return $this;
    }

    /**
     * Note: 限制关联模型的字段
     * Date: 2023-05-24
     * Time: 17:47
     * @param array $field 字段
     * @return $this
     */
    public function withField(array $field)
    {
        $this->withField = $field;

        return $this;
    }

    /**
     * Note: 排除关联模型的字段
     * Date: 2023-05-24
     * Time: 17:48
     * @param string|array $field 字段
     * @return $this
     */
    public function withoutField($field)
    {
        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $this->withoutField = $field;

        return $this;
    }

    /**
     * Note: 获取关联模型查询条件
     * Date: 2023-05-23
     * Time: 11:12
     * @param array $where 查询条件
     * @param string $relation 关联模型类
     * @return void
     */
    protected function getRelationQueryWhere(array &$where, string $relation)
    {
        foreach ($where as $key => &$val) {
            if (is_string($key)) {
                $where[] = [strpos($key, '.') === false ? $relation . '.' . $key : $key, '=', $val];
                unset($where[$key]);
            } elseif (isset($val[0]) && false === strpos($val[0], '.')) {
                $val[0] = $relation . '.' . $val[0];
            }
        }
    }

    /**
     * Note: 封装关联数据集
     * Date: 2023-06-07
     * Time: 14:49
     * @param array $resultSet 数据集合
     * @param Model $parent 父模型
     * @return mixed
     */
    protected function resultSetBuild(array $resultSet, Model $parent)
    {
        return (new $this->model)->toCollection($resultSet)->setParent($parent);
    }

    /**
     * Note: 获取模型的查询字段
     * Date: 2023-06-12
     * Time: 17:18
     * @param string $model
     * @return array|string
     */
    protected function getQueryFields(string $model)
    {
        $fields = $this->query->getOptions('field');

        return $this->getRelationQueryFields($fields, $model);
    }

    /**
     * Note: 获取关联模型查询字段
     * Date: 2023-05-23
     * Time: 11:41
     * @param string|array $fields 字段
     * @param string $relation 关联模型类
     * @return string
     */
    protected function getRelationQueryFields($fields, string $relation)
    {
        if (empty($fields) || $fields == '*') {
            return $relation . '.*';
        }

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        foreach ($fields as &$field) {
            if (strpos($fields, '.') === false) {
                $fields = $relation . '.' . $fields;
            }
        }

        return $fields;
    }

    /**
     * Note: 获取闭包的参数类型
     * Date: 2023-05-26
     * Time: 11:31
     * @param Closure $closure 闭包
     * @return mixed
     */
    protected function getClosureType(Closure $closure)
    {
        $reflect = new ReflectionFunction($closure);
        $params = $reflect->getParameters();

        if (!empty($params)) {
            $type = $params[0]->getType();

            if (is_null($type) || $type->getName() == Relation::class) {
                return $this;
            } else {
                return $this->query;
            }
        }

        return $this;
    }

    /**
     * Note: 更新记录
     * Date: 2023-06-07
     * Time: 17:55
     * @param array $data 更新数据
     * @return int
     */
    public function update(array $data = [])
    {
        return $this->query->update($data);
    }

    /**
     * Note: 删除记录
     * Date: 2023-06-07
     * Time: 17:52
     * @param mixed $data 表达式true:强制删除
     * @return int
     * @throws DbException
     */
    public function delete($data = null)
    {
        return $this->query->delete($data);
    }

    /**
     * Note: 查询
     * Date: 2023-05-22
     * Time: 17:29
     * @return void
     */
    abstract protected function baseQuery();

    public function __call($method, $args)
    {
        if ($this->query) {
            $this->baseQuery();

            $result = call_user_func_array([$this->query, $method], $args);

            return $result === $this->query ? $this : $result;
        }

        throw new DbException('method not exists:' . __CLASS__ . '->' . $method);
    }
}