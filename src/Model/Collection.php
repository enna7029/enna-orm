<?php
declare(strict_types=1);

namespace Enna\Orm\Model;

use Enna\Framework\Helper\Collection as BaseCollection;
use Enna\Orm\Model;

/**
 * 模型数据集合类
 * Class Collection
 * @package Enna\Orm\Model
 */
class Collection extends BaseCollection
{
    /**
     * Note: 延迟预载入关联查询
     * Date: 2023-05-20
     * Time: 14:17
     * @param array|string $relation 关联
     * @param bool $cache 关联缓存
     * @return $this
     */
    public function load($relation, $cache = false)
    {
        if (!$this->isEmpty()) {
            $item = current($this->items);
            $item->eagerlyResultSet($this->items, (array)$relation, [], false, $cache);
        }

        return $this;
    }

    /**
     * Note: 删除数据集对象
     * Date: 2023-05-20
     * Time: 14:21
     * @return bool
     */
    public function delete()
    {
        $this->each(function (Model $model) {
            $model->delete();
        });

        return true;
    }

    /**
     * Note: 更新数据
     * Date: 2023-05-20
     * Time: 14:25
     * @param array $data 数据数组
     * @param array $allowField 允许字段
     * @return bool
     */
    public function update(array $data, array $allowField = [])
    {
        $this->each(function (Model $model) use ($data, $allowField) {
            if (!empty($allowField)) {
                $model->allowField($allowField);
            }

            $model->save($data, $allowField);
        });

        return true;
    }

    /**
     * Note: 设置需要隐藏的输出属性
     * Date: 2023-05-20
     * Time: 14:33
     * @param array $hidden 属性列表
     * @return $this
     */
    public function hidden(array $hidden)
    {
        $this->each(function (Model $model) use ($hidden) {
            $model->hidden($hidden);
        });

        return $this;
    }

    /**
     * Note: 设置需要显示的输出属性
     * Date: 2023-05-20
     * Time: 14:33
     * @param array $visible 属性列表
     * @return $this
     */
    public function visible(array $visible)
    {
        $this->each(function (Model $model) use ($visible) {
            $model->visible($visible);
        });

        return $this;
    }

    /**
     * Note: 设置需要追加的输出属性
     * Date: 2023-05-20
     * Time: 14:33
     * @param array $append 属性列表
     * @return $this
     */
    public function append(array $append)
    {
        $this->each(function (Model $model) use ($append) {
            $model->append($append);
        });

        return $this;
    }

    /**
     * Note: 设置输出场景
     * Date: 2023-05-20
     * Time: 15:32
     * @param string $scene 场景名称
     * @return $this
     */
    public function scene(string $scene)
    {
        $this->each(function (Model $model) use ($scene) {
            $model->scene($scene);
        });
    }

    /**
     * Note: 设置父模型
     * Date: 2023-05-20
     * Time: 15:41
     * @param Model $parent 父模型
     * @return $this
     */
    public function setParent(Model $parent)
    {
        $this->each(function (Model $model) use ($parent) {
            $model->setParent($parent);
        });

        return $this;
    }

    /**
     * Note: 设置字段获取器
     * Date: 2023-05-20
     * Time: 15:42
     * @param string|array $name 字段名
     * @param callable $callback 闭包
     * @return $this
     */
    public function withAttr($name, callable $callback)
    {
        $this->each(function (Model $model) use ($name, $callback) {
            $model->withAttribute($name, $callback);
        });

        return $this;
    }

    /**
     * Note: 绑定关联属性到当前模型
     * Date: 2023-05-20
     * Time: 15:45
     * @param string $relation 关联名称
     * @param array $attrs 绑定属性
     * @return $this
     */
    public function bindAttr(string $relation, array $attrs = [])
    {
        $this->each(function (Model $model) use ($relation, $attrs) {
            $model->bindAttr($relation, $attrs);
        });

        return $this;
    }

    /**
     * Note: 按指定键整理数据
     * Date: 2023-05-20
     * Time: 15:58
     * @param mixed $items 数据
     * @param string|null $indexKey 键
     * @return array|void
     */
    public function dictionary($items = null, string &$indexKey = null)
    {
        if ($items instanceof self) {
            $items = $items->all();
        }

        $items = is_null($items) ? $this->items = $items;

        if ($items && empty($indexKey)) {
            $indexKey = $items[0]->getPk();
        }

        if (isset($indexKey) && is_string($indexKey)) {
            return array_column($items, null, $indexKey);
        }

        return $items;
    }

    /**
     * Note: 比较数据集,返回差集
     * Date: 2023-05-20
     * Time: 16:00
     * @param mixed $items 数据
     * @param string|null $indexKey 键
     * @return Collection
     */
    public function diff($items, string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static($this->items);
        }

        $diff = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (!isset($dictionary[$item[$indexKey]])) {
                    $diff[] = $item;
                }
            }
        }

        return new static($diff);
    }

    /**
     * Note: 比较数据集,返回差集
     * Date: 2023-05-20
     * Time: 16:00
     * @param mixed $items 数据
     * @param string|null $indexKey 键
     * @return Collection
     */
    public function intersect($items, string $indexKey = null)
    {
        if ($this->isEmpty()) {
            return new static($this->items);
        }

        $intersect = [];
        $dictionary = $this->dictionary($items, $indexKey);

        if (is_string($indexKey)) {
            foreach ($this->items as $item) {
                if (isset($dictionary[$item[$indexKey]])) {
                    $intersect[] = $item;
                }
            }
        }

        return new static($intersect);
    }
}