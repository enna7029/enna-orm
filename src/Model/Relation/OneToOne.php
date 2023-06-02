<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Orm\Db\BaseQuery as Query;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model\Relation;
use Enna\Framework\Helper\Str;
use ReflectionFunction;

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
    public function eagerly(Query $query, string $relation, $field, string $joinType = '', Closure $closure = null, bool $first = false)
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
}