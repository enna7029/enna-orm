<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model\Relation;
use Enna\Framework\Helper\Str;
use ReflectionFunction;
use Enna\Orm\Model;

/**
 * 一对一关联基础类
 * Class OneToOne
 * @package Enna\Orm\Model\Relation
 */
abstract class OneToOne extends Relation
{
    /**
     * JOIN类型
     * @var string
     */
    protected $joinType = 'INNER';

    /**
     * 绑定的关联属性
     * @var array
     */
    protected $bindAttr = [];

    /**
     * 关联名
     * @var string
     */
    protected $relation;

    /**
     * Note: 设置JOIN类型
     * Date: 2023-06-06
     * Time: 17:20
     * @param string $type JOIN类型
     * @return $this
     */
    public function joinType(string $type)
    {
        $this->joinType = $type;

        return $this;
    }

    /**
     * Note: 绑定关联模型的属性到父模型属性
     * Date: 2023-05-25
     * Time: 9:50
     * @param array $attr 属性
     * @return $this
     */
    public function bind(array $attr)
    {
        $this->bindAttr = $attr;

        return $this;
    }

    /**
     * Note: 获取绑定到父模型的属性
     * Date: 2023-05-25
     * Time: 9:51
     * @return array
     */
    public function getBindAttr()
    {
        return $this->bindAttr;
    }

    /**
     * Note: 预载入关联查询:JOIN方式
     * Date: 2023-05-24
     * Time: 14:16
     * @param Query $query 查询对象
     * @param string $relation 关联方法名
     * @param mixed $field 字段
     * @param string $joinType JOIN类型
     * @param Closure $closure 闭包
     * @param bool $first 是否第一次关联
     * @return void
     */
    public function withJoin(Query $query, string $relation, $field, string $joinType = '', Closure $closure = null, bool $first = false)
    {
        $name = Str::snake(class_basename($this->parent));

        if ($first) {
            $table = $query->getTable();
            $query->table([$table => $name]);

            if ($query->getOptions('field')) {
                $masterField = $query->getOptions('field');
                $query->removeOption('field');
            } else {
                $masterField = true;
            }

            $query->tableField($masterField, $table, $name);
        }

        $joinTable = $this->query->getTable();
        $joinAlias = $relation;
        $joinType = $joinType ?: $this->joinType;

        $query->via($joinAlias);

        if ($this instanceof BelongsTo) {
            $joinOn = $name . '.' . $this->foreignKey . '=' . $joinAlias . '.' . $this->localKey;
        } else {
            $joinOn = $name . '.' . $this->localKey . '=' . $joinAlias . '.' . $this->foreignKey;
        }

        if ($closure) {
            $closure($this->getClosureType($closure));

            if ($this->withField) {
                $field = $this->withField;
            }
        }

        $query->join([$joinTable => $joinAlias], $joinOn, $joinType)
            ->tableField($field, $joinTable, $joinAlias, $relation . '__');
    }

    /**
     * Note: 绑定关联属性到父模型
     * Date: 2023-05-26
     * Time: 17:54
     * @param Model $parent 父模型对象
     * @param Model $model 关联模型对象
     * @return void
     * @throws DbException
     */
    protected function bindAttr(Model $parent, Model $model)
    {
        foreach ($this->bindAttr as $key => $attr) {
            $key = is_numeric($key) ? $attr : $key;
            $value = $parent->getOrigin($key);

            if (!is_null($value)) {
                throw new DbException('bind attr has exists:' . $key);
            }

            $parent->setAttr($key, $model ? $model->$attr : null);
        }
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-06
     * Time: 16:53
     * @param array $resultSet 数据集
     * @param string $relation 当前关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @param bool $join 是否为Join方式
     * @return void
     */
    public function withQuerySet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [], bool $join = false)
    {
        if ($join) {
            foreach ($resultSet as $result) {
                $this->match($this->model, $relation, $result);
            }
        } else {
            $this->withSet($resultSet, $relation, $subRelation, $closure, $cache);
        }
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-03
     * Time: 17:19
     * @param Model $result 模型对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @param bool $join 是否JOIN方式
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [], bool $join = false)
    {
        if ($join) {
            $this->match($this->model, $relation, $result); //JOIN方式
        } else {
            $this->withOne($result, $relation, $subRelation, $closure, $cache); //IN方式
        }
    }

    /**
     * Note: 关联模型预查询拼装
     * Date: 2023-06-06
     * Time: 14:00
     * @param string $model
     * @param string $relation
     * @param Model $result
     */
    protected function match(string $model, string $relation, Model $result)
    {
        foreach ($result->getData() as $key => $val) {
            if (strpos($key, '__')) {
                [$name, $attr] = explode(',', $key, 2);
                if ($name == $relation) {
                    $list[$name][$attr] = $val;
                    unset($result->$key);
                }
            }
        }

        if (isset($list[$relation])) {
            $array = array_unique($list[$relation]);

            if (count($array) == 1 && current($array) == null) {
                $relationModel = null;
            } else {
                $relationModel = new $model($list[$relation]);
                $relationModel->setParent(clone $result);
                $relationModel->exists(true);
            }

            if (!empty($this->bindAttr)) {
                $this->bindAttr($result, $relationModel);
            }
        } else {
            $relationModel = null;
        }

        $result->setRelation($relation, $relationModel);
    }

    /**
     * Note: 关联模型查询(IN方式)
     * Date: 2023-06-06
     * Time: 11:25
     * @param array $where 关联预查询条件
     * @param string $key 关联键名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withWhere(array $where, string $key, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        if ($closure) {
            $this->baseQuery = true;
            $closure($this->getClosureType($closure));
        }

        if ($this->withField) {
            $this->query->field($this->withField);
        } elseif ($this->withoutField) {
            $this->query->withoutField($this->withoutField);
        }

        $list = $this->query
            ->where($where)
            ->with($subRelation)
            ->cache($cache[0] ?? null, $cache[1] ?? null, $cache[2] ?? null)
            ->select();

        $data = [];
        foreach ($list as $item) {
            if (!isset($data[$item->$key])) {
                $data[$item->$key] = $item;
            }
        }

        return $data;
    }

    /**
     * Note: 保存(新增)当前关联数据对象
     * Date: 2023-06-06
     * Time: 17:38
     * @param Model|array $data 数据:模型对象或数组
     * @param bool $replace 是否自动识别更新或写入
     * @return Model|false
     */
    public function save($data, bool $replace = true)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }

        $model = new $this->model;
        $data[$this->foreignKey] = $this->parent->{$this->localKey};

        return $model->replace($replace)->save($data) ? $model : false;
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-03
     * Time: 17:33
     * @param Model $result 模型对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return mixed
     */
    abstract protected function withOne(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = []);

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-06
     * Time: 10:16
     * @param array $resultSet 模型集合
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return mixed
     */
    abstract protected function withSet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = []);
}