<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Framework\Helper\Str;
use Enna\Framework\Helper\Collection;
use Enna\Orm\Db\Exception\DataNotFoundException;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Db\Exception\ModelNotFoundException;

/**
 * 查询数据处理
 * Trait ResultOperation
 * @package Enna\Orm\Db\Concern
 */
trait ResultOperation
{
    /**
     * Note: 查找多条记录,不存在抛出异常
     * Date: 2023-04-20
     * Time: 9:57
     * @param mixed $data 要查询的数据
     * @return mixed
     */
    public function selectOrFail($data = null)
    {
        return $this->failException(true)->select($data);
    }

    /**
     * Note: 查找单条记录,不存在抛出异常
     * Date: 2023-04-20
     * Time: 9:58
     * @param mixed $data 要查询的数据
     * @return mixed
     */
    public function findOrFail($data = null)
    {
        return $this->failException(true)->find($data);
    }

    /**
     * Note: 查找单条记录,不存在则返回空数组
     * Date: 2023-04-20
     * Time: 14:03
     * @param mixed $data 数据
     * @return array
     */
    public function findOrEmpty($data = null)
    {
        return $this->allowEmpty(true)->find($data);
    }

    /**
     * Note: 设置查询数据不存在时抛出异常
     * Date: 2023-04-20
     * Time: 9:54
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    public function failException(bool $fail = true)
    {
        $this->options['fail'] = $fail;

        return $this;
    }

    /**
     * Note: 是否允许返回空数组
     * Date: 2023-04-20
     * Time: 14:05
     * @param bool $allowEmpty 是否允许为空
     * @return $this
     */
    public function allowEmpty(bool $allowEmpty = true)
    {
        $this->options['allow_empty'] = $allowEmpty;

        return $this;
    }

    /**
     * Note: 处理空数据
     * Date: 2023-04-20
     * Time: 14:35
     * @return array
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    protected function resultToEmpty()
    {
        if (!empty($this->options['fail'])) {
            $this->throwNotFound();
        } elseif (!empty($this->options['allow_empty'])) {
            return !empty($this->model) ? $this->model->newInstance() : [];
        }
    }

    /**
     * Note: 查询失败,抛出异常
     * Date: 2023-04-20
     * Time: 10:05
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     */
    protected function throwNotFound()
    {
        if (!empty($this->model)) {
            $class = get_class($this->model);
            throw new ModelNotFoundException('model data not found' . $class, $class, $this->options);
        }

        $table = $this->getTable();
        throw new DataNotFoundException('table data not found' . $table, $table, $this->options);
    }

    /**
     * Note: 处理数据集
     * Date: 2023-04-20
     * Time: 11:21
     * @param array $resultSet 数据集
     * @return void
     */
    public function resultSet(array &$resultSet)
    {
        if (!empty($this->options['json'])) {
            foreach ($resultSet as &$result) {
                $this->jsonResult($result, $this->options['json'], true);
            }
        }

        if (!empty($this->options['with_attr'])) {
            foreach ($resultSet as &$result) {
                $this->getResultAttr($result, $this->options['with_attr']);
            }
        }

        if (!empty($this->options['visiable']) || !empty($this->options['hidden'])) {
            foreach ($resultSet as &$result) {
                $this->filterResult($result);
            }
        }

        $resultSet = new Collection($resultSet);
    }

    /**
     * Note: 处理数据
     * Date: 2023-04-20
     * Time: 14:37
     * @param array $result 数据
     * @return void
     */
    public function result(array &$result)
    {
        if (!empty($this->options['json'])) {
            $this->jsonResult($result, $this->options['json'], true);
        }

        if (!empty($this->options['with_attr'])) {
            $this->getResultAttr($result, $this->options['with_attr']);
        }

        $this->filterResult($result);
    }

    /**
     * Note: JSON字段数据转换
     * Date: 2023-04-20
     * Time: 11:35
     * @param array $result 数据
     * @param array $json JSON字段
     * @param bool $assoc 是否转换为数组
     * @param array $withRelationAttr 关联获取器
     * @return void
     */
    protected function jsonResult(array &$result, array $json = [], bool $assoc = false, array $withRelationAttr = [])
    {
        foreach ($json as $name) {
            if (!isset($result[$name])) {
                continue;
            }

            $result[$name] = json_decode($result[$name], true);

            if (isset($withRelationAttr[$name])) {
                foreach ($withRelationAttr[$name] as $key => $closure) {
                    $result[$name][$key] = $closure($result[$name][$key] ?? null, $result[$name]);
                }
            }

            if (!$assoc) {
                $result[$name] = (object)$result[$name];
            }
        }
    }

    /**
     * Note: 使用获取器处理数据
     * Date: 2023-04-20
     * Time: 11:40
     * @param array $result 数据
     * @param array $withAttr 字段获取器
     * @return void
     */
    protected function getResultAttr(array &$result, array $withAttr = [])
    {
        foreach ($withAttr as $name => $closure) {
            $name = Str::snake($name);

            if (strpos($name, '.')) {
                [$key, $field] = explode('.', $name);

                if (isset($result[$key])) {
                    $result[$key][$field] = $closure($result[$key][$field] ?? null, $result);
                }
            } else {
                $result[$name] = $closure($result[$name] ?? null, $result);
            }
        }
    }

    /**
     * Note: 处理数据的可见和隐藏
     * Date: 2023-04-20
     * Time: 11:44
     * @param array $result 数据
     * @return void
     */
    protected function filterResult(array &$result)
    {
        $array = [];
        if (!empty($this->options['visiable'])) {
            foreach ($this->options['visiable'] as $key) {
                $array[] = $key;
            }

            $result = array_intersect_key($result, array_flip($array));
        } elseif (!empty($this->options['hidden'])) {
            foreach ($this->options['hidden'] as $key) {
                $array[] = $key;
            }

            $result = array_diff_key($result, array_flip($array));
        }
    }
}