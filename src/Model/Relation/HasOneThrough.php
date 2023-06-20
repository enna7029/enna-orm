<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Relation;

use Closure;
use Enna\Framework\Helper\Str;
use Enna\Orm\Model;

/**
 * 远程一对一关联
 * Class HasOneThrough
 * @package Enna\Orm\Model\Relation
 */
class HasOneThrough extends HasManyThrough
{
    /**
     * Note: 延迟获取关联数据
     * Date: 2023-06-14
     * Time: 11:28
     * @param array $subRelation 子关联名
     * @param Closure|null $closure 比表查询条件
     * @return Model
     */
    public function getRelation(array $subRelation = [], Closure $closure = null)
    {
        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $this->baseQuery();

        $relationModel = $this->query->relation($subRelation)->find();

        if ($relationModel) {
            $relationModel->setParent(clone $this->parent);
        }

        return $relationModel;
    }

    /**
     * Note: 预载入关联查询(数据集)
     * Date: 2023-06-14
     * Time: 11:45
     * @param array $resultSet
     * @param string $relation
     * @param array $subRelation
     * @param Closure|null $closure
     * @param array $cache
     */
    public function withQuerySet(array &$resultSet, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;
        $foreignKey = $this->foreignKey;

        $range = [];
        foreach ($resultSet as $result) {
            if (isset($result->$localKey)) {
                $range[] = $result->$localKey;
            }
        }

        if (!empty($range)) {
            $this->query->removeWhereField($foreignKey);

            $data = $this->withWhere([
                [$this->foreignKey, 'in', $range]
            ], $foreignKey, $subRelation, $closure, $cache);

            foreach ($resultSet as $result) {
                if (!isset($data[$result->$localKey])) {
                    $relationModel = null;
                } else {
                    $relationModel = $data[$result->$localKey];
                    $relationModel->setParent(clone $result);
                    $relationModel->exists(true);
                }

                $result->setRelation($relation, $relationModel);
            }
        }
    }

    /**
     * Note: 预载入关联查询(数据)
     * Date: 2023-06-14
     * Time: 14:17
     * @param Model $result 数据对象
     * @param string $relation 关联方法名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return void
     */
    public function withQuery(Model $result, string $relation, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $localKey = $this->localKey;
        $foreignKey = $this->foreignKey;

        $this->query->removeWhereField($foreignKey);

        $data = $this->withWhere([
            [$foreignKey, '=', $result->$localKey]
        ], $foreignKey, $subRelation, $closure, $cache);

        if (!isset($data[$result->$localKey])) {
            $relationModel = null;
        } else {
            $relationModel = $data[$result->$localKey];
            $relationModel->setParent(clone $result);
            $relationModel->exists(true);
        }

        $result->serRelation($relation, $relationModel);
    }

    /**
     * Note: 关联模型预查询
     * Date: 2023-06-14
     * Time: 14:02
     * @param array $where 关联预查询条件
     * @param string $key 关联键名
     * @param array $subRelation 子关联方法名
     * @param Closure|null $closure 闭包
     * @param array $cache 关联缓存
     * @return array
     */
    protected function withWhere(array $where, string $key, array $subRelation = [], Closure $closure = null, array $cache = [])
    {
        $keys = $this->through->where($where)->column($this->throughPk, $this->foreignKey);

        if ($closure) {
            $closure($this->getClosureType($closure));
        }

        $list = $this->query
            ->where($this->throughKey, 'in', $keys)
            ->cache($cache[0] ?? null, $cache[1] ?? null, $cache[2] ?? null)
            ->select();

        $data = [];
        $keys = array_flip($keys);
        foreach ($list as $set) {
            $data[$keys[$set->{$this->throughKey}]] = $set;
        }

        return $data;
    }
}