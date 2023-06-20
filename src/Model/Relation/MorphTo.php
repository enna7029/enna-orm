<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model;
use Enna\Orm\Model\Relation;
use Enna\Framework\Helper\Str;

class MorphTo extends Relation
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
     * 多态别名
     * @var array
     */
    protected $alias = [];

    /**
     * 关联名
     * @var string
     */
    protected $relation;

    /**
     * MorphTo constructor.
     * @param Model $parent 父模型
     * @param string $morphType 多态字段:类型
     * @param string $morphKey 多态字段:外键
     * @param array $alias 多态别名
     * @param string|null $relation 关联名
     */
    public function __construct(Model $parent, string $morphType, string $morphKey, array $alias = [], string $relation = null)
    {
        $this->parent = $parent;

        $this->morphType = $morphType;
        $this->morphKey = $morphKey;
        $this->alias = $alias;
        $this->relaion = $relation;
    }

    /**
     * Note: 获取当前的关联模型类的实例
     * Date: 2023-06-19
     * Time: 17:49
     * @return Model
     */
    public function getModel()
    {
        $morphType = $this->morphType;
        $model = $this->parseModel($this->parent->$morphType);

        return (new $model);
    }

    /**
     * Note: 延迟获取关联数据
     * Date: 2023-06-19
     * Time: 17:57
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 闭包查询条件
     * @return Model
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        $morphKey = $this->morphKey;
        $morphType = $this->morphType;

        $model = $this->parseModel($this->parent->$morphType);
        $pk = $this->parent->$morphKey;

        $relationModel = (new $model)->relation($subRelation)->find($pk);
        if ($relationModel) {
            $relationModel->setParent(clone $this->parent);
        }

        return $relationModel;
    }

    /**
     * Note: 根据关联条件查询当前模型
     * Date: 2023-06-19
     * Time: 17:57
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
     * Date: 2023-06-19
     * Time: 17:57
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
     * Note: 解析模型的完整命名空间
     * Date: 2023-06-19
     * Time: 18:09
     * @param string $model
     * @return mixed|string
     */
    protected function parseModel(string $model)
    {
        if (isset($this->alias[$model])) {
            $model = $this->alias[$model];
        }

        if (strpos($model, '\\') === false) {
            $path = explode('\\', get_class($this->parent));
            array_pop($path);
            array_push($path, Str::studly($model));
            $model = implode('\\', $path);
        }

        return $model;
    }

    /**
     * Note: 设置多态别名
     * Date: 2023-06-19
     * Time: 18:11
     * @param array $alias 别名定义
     * @return $this
     */
    public function setAlias(array $alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-19
     * Time: 18:23
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

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$morphKey) && !empty($result->$morphKey)) {
                $range[$result->$morphType][] = $result->$morphKey;
            }
        }

        if (!empty($range)) {
            foreach ($range as $key => $val) {
                $model = $this->parseModel($key);

                $obj = new $model;
                $list = $obj->with($subRelation)
                    ->cache($cache[0] ?? false, $cache[1] ?? null, $cache[2] ?? null)
                    ->select($val);

                $data = [];
                $pk = $obj->getPk();
                foreach ($list as $k => $item) {
                    $data[$item->$pk] = $item;
                }

                foreach ($resultSet as $result) {
                    if ($result->$morphType == $key) {
                        if (!isset($data[$result->$morphKey])) {
                            $relationModel = null;
                        } else {
                            $relationModel = $data[$result->$morphKey];
                            $relationModel->setParent(clone $result);
                            $relationModel->exists(true);
                        }

                        $result->setRelation($relation, $relationModel);
                    }
                }
            }
        }
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-19
     * Time: 18:23
     * @param Model $result 数据对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 是否关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $morphType = $this->morphType;
        $model = $this->parseModel($result->$morphType);

        $morphKey = $this->morphKey;
        $pk = $this->parent->$morphKey;

        $data = (new $model)->with($subRelation)
            ->cache($cache[0] ?? false, $cache[1] ?? null, $cache[2] ?? null)
            ->find($pk);
        if ($data) {
            $data->setParent(clone $result);
            $data->exists(true);
        }

        $result->setRelation($relation, $data ?? null);
    }

    /**
     * Note: 关联统计
     * Date: 2023-06-20
     * Time: 9:52
     * @param Model $result 数据对象
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return int
     */
    public function getRelationAggreate(Model $result, Closure $closure = null, string $aggreate = 'count', string $field = '*', string &$name = null)
    {
        $morphType = $this->morphType;
        $model = $this->parseModel($morphType);

        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        $morphKey = $this->morphKey;
        $pk = (new $model)->getPk();
        $key = $result->$morphKey;

        return (new $model)->where([
            [$pk, '=', $key]
        ])->$aggreate($field);
    }

    /**
     * Note: 创建关联统计子查询
     * Date: 2023-06-20
     * Time: 9:53
     * @param Closure|null $closure 闭包
     * @param string $aggreate 聚合查询方法
     * @param string $field 字段
     * @param string|null $name 统计字段别名
     * @return string
     */
    public function getRelationAggreateQuery(Closure $closure = null, string $aggreate = 'count', string $field = '*', string &$name = null)
    {
        $morphType = $this->morphType;
        $model = $this->parseModel($morphType);

        if ($closure) {
            $closure($this->getClosureType($closure), $name);
        }

        return (new $model)
            ->where($this->morphKey, '=', (new $model)->getTable() . '.' . (new $model)->getPk())
            ->fetchSql()
            ->$aggreate($field);
    }

    /**
     * Note: 添加关联数据
     * Date: 2023-06-20
     * Time: 10:23
     * @param Model $model 关联模型对象
     * @param string $type 多态类型
     * @return Model
     */
    public function associate(Model $model, string $type = '')
    {
        $morphKey = $this->morphKey;
        $morphType = $this->morphType;
        $pk = $model->getPk();

        $this->parent->setAttr($morphKey, $model->$pk);
        $this->parent->setAttr($morphType, $type ?: get_class($model));
        $this->parent->save();

        return $this->parent->setRelation($this->relation, $model);
    }

    /**
     * Note: 删除关联数据
     * Date: 2023-06-20
     * Time: 10:29
     * @return Model
     */
    public function dissociate()
    {
        $morphKey = $this->morphKey;
        $morphType = $this->morphType;

        $this->parent->setAttr($morphKey, null);
        $this->parent->setAttr($morphType, null);
        $this->parent->save();

        return $this->parent->setRelation($this->relation, null);
    }
}