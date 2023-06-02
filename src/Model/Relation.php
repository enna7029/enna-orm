<?php

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
            }
        }
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