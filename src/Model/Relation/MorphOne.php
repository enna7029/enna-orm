<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Enna\Orm\Db\Exception\DbException;

/**
 * 多态一对一关联
 * Class MorphOne
 * @package Enna\Orm\Model\Relation
 */
class MorphOne extends Relation
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
     * 绑定的关联属性
     * @var array
     */
    protected $bindAttr = [];

    /**
     * MorphOne constructor.
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
     * Date: 2023-06-20
     * Time: 11:12
     * @param array $subRelation 子关联方法名
     * @param Closure $closure 闭包
     * @return Model
     */
    public function getRelation(array $subRelation = [], Closure $closure)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $this->baseQuery();
        $relationModel = $this->query->relation($subRelation)->find();
        if ($relationModel) {
            if (!empty($this->bindAttr)) {
                $this->bindAttr($this->parent, $relationModel);
            }

            $relationModel->setParent(clone $this->parent);
        } else {
            $relationModel = $this->getDefaultModel();
        }

        return $relationModel;
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-20
     * Time: 11:25
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
     * Date: 2023-06-20
     * Time: 11:25
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
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-20
     * Time: 11:37
     * @param Model $result 数据对象
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
            $data = $this->withQueryMorphToOne([
                [$morphKey, 'in', $range],
                [$morphType, '=', $type]
            ], $subRelation, $closure, $cache);

            foreach ($resultSet as $result) {
                $pk = $result->getPk();
                if (!isset($data[$result->$pk])) {
                    $relationModel = $this->getDefaultModel();
                } else {
                    $relationModel = $data[$result->$pk];
                    $relationModel->setParent(clone $result);
                    $relationModel->exists(true);
                }

                if (!empty($this->bindAttr)) {
                    $this->bindAttr($result, $relationModel);
                } else {
                    $result->setRelation($relation, $relationModel);
                }
            }
        }
    }

    /**
     * Note: 多态一对多,关联模型预查询
     * Date: 2023-06-20
     * Time: 11:37
     * @param array $where 关联预查询条件
     * @param array $subRelation 子关联
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
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
                $relationModel = $this->getDefaultModel();
            } else {
                $relationModel = $data[$key];
                $relationModel->setParent(clone $result);
                $relationModel->exists(true);
            }

            if (!empty($this->bindAttr)) {
                $this->bindAttr($result, $relationModel);
            } else {
                $this->setRelation($relation, $relationModel);
            }
        }
    }

    /**
     * Note: 多态一对一,关联模型预查询
     * Date: 2023-06-20
     * Time: 11:39
     * @param array $where 关联预查询条件
     * @param array $subRelation 子关联
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withQueryMorphToOne(array $where, array $subRelation = [], Closure $closure = null, array $cache = [])
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
            $data[$set->$morphKey] = $set;
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
     * Date: 2023-06-20
     * Time: 11:50
     * @param array|Model $data 数据
     * @param bool $replace 是否自动识别更新和写入
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
     * Note: 绑定关联表的属性到父模型属性
     * Date: 2023-06-20
     * Time: 11:25
     * @param array $attr 要绑定的属性列表
     * @return $this
     */
    public function bind(array $attr)
    {
        $this->bindAttr = $attr;

        return $this;
    }

    /**
     * Note: 获取绑定属性
     * Date: 2023-06-20
     * Time: 11:26
     * @return array
     */
    public function getBindAttr()
    {
        return $this->bindAttr;
    }

    /**
     * Note: 绑定关联属性到父模型
     * Date: 2023-06-20
     * Time: 11:28
     * @param Model $result 父模型对象
     * @param Model|null $model 关联模型对象
     * @return void
     * @throws DbException
     */
    protected function bindAttr(Model $result, Model $model = null)
    {
        foreach ($this->bindAttr as $key => $attr) {
            $key = is_numeric($key) ? $attr : $key;
            $value = $result->getOrigin($key);
            if (!is_null($value)) {
                throw new DbException('bind attr has exists:' . $key);
            }

            $result->setAttr($key, $model ? $model->$attr : null);
        }
    }

    /**
     * Note: 创建关联对象实例
     * Date: 2023-06-20
     * Time: 11:13
     * @return Model
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