<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Concern;

use Enna\Framework\Helper\Str;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model;
use Enna\Orm\Model\Collection as ModelCollection;
use Enna\Orm\Model\Relation\OneToOne;

/**
 * 模型数据转换处理
 * Trait Conversion
 * @package Enna\Orm\Model\Concern
 */
trait Conversion
{
    /**
     * 显示的属性
     * @var array
     */
    protected $visible = [];

    /**
     * 隐藏的属性
     * @var array
     */
    protected $hidden = [];

    /**
     * 追加的属性
     * @var array
     */
    protected $append = [];

    /**
     * 场景
     * @var array
     */
    protected $scene = [];

    /**
     * 字段映射
     * @var array
     */
    protected $mapping = [];

    /**
     * 数据集对象名
     * @var string
     */
    protected $resultSetType;

    /**
     * 数据命名是否自动转换为驼峰
     * @var bool
     */
    protected $convertNameToCamel;

    /**
     * Note: 输出时转换为驼峰
     * Date: 2023-05-20
     * Time: 14:41
     * @param bool $toCamel 是否自动驼峰命名
     * @return $this
     */
    public function convertNameToCamel(bool $toCamel = true)
    {
        $this->convertNameToCamel = $toCamel;

        return $this;
    }

    /**
     * Note: 设置隐藏的属性
     * Date: 2023-05-20
     * Time: 14:41
     * @param array $hidden 属性列表
     * @param bool $merge 是否合并
     * @return $this
     */
    public function hidden(array $hidden = [], bool $merge = false)
    {
        if ($merge) {
            $this->hidden = array_merge($this->hidden, $hidden);
        } else {
            $this->hidden = $hidden;
        }

        return $this;
    }

    /**
     * Note: 设置输出的属性
     * Date: 2023-05-20
     * Time: 15:30
     * @param array $visible 属性列表
     * @param bool $merge 是否合并
     * @return $this
     */
    public function visible(array $visible = [], bool $merge = false)
    {
        if ($merge) {
            $this->visible = array_merge($this->visible, $visible);
        } else {
            $this->visible = $visible;
        }

        return $this;
    }

    /**
     * Note: 设置追加的属性
     * Date: 2023-05-20
     * Time: 15:31
     * @param array $append 属性列表
     * @param bool $merge 是否合并
     * @return $this
     */
    public function append(array $append = [], bool $merge = false)
    {
        if ($merge) {
            $this->append = array_merge($this->append, $append);
        } else {
            $this->append = $append;
        }

        return $this;
    }

    /**
     * Note: 设置输出场景
     * Date: 2023-05-20
     * Time: 15:34
     * @param string $scene 场景名称
     * @return $this
     */
    public function scene(string $scene)
    {
        if (isset($this->scene[$scene])) {
            $data = $this->scene[$scene];
            foreach (['append', 'hidden', 'visible'] as $name) {
                if (isset($data[$name])) {
                    $this->$name($data[$name]);
                }
            }
        }

        return $this;
    }

    /**
     * Note: 设置属性的映射输出
     * Date: 2023-11-09
     * Time: 18:08
     * @param array $map
     * @return $this
     */
    public function mapping(array $map)
    {
        $this->mapping = $map;

        return $this;
    }

    /**
     * Note: 设置附加关联对象的属性
     * Date: 2023-05-20
     * Time: 16:46
     * @param string $attr 关联属性
     * @param array $append 追加属性名
     * @return $this
     * @throws DbException
     */
    public function appendRelationAttr(string $attr, array $append)
    {
        $relation = Str::camel($attr);

        if (isset($this->relation[$relation])) {
            $model = $this->relation[$relation];
        } else {
            $model = $this->getRelationData($this->$relation());
        }

        if ($model instanceof Model) {
            foreach ($append as $key => $attr) {
                $key = is_numeric($key) ? $attr : $key;
                if (isset($this->data[$key])) {
                    throw new DbException('bind attr has exists:' . $key);
                }

                $this->data[$key] = $model->$attr;
            }
        }

        return $this;
    }

    /**
     * Note: 转换当前对象模型为数组
     * Date: 2023-05-20
     * Time: 16:50
     * @return array
     */
    public function toArray(): array
    {
        $item = $visible = $hidden = [];
        $hasVisible = false;

        //显示属性数据
        foreach ($this->visible as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    [$relation, $name] = explode('.', $val);
                    $visible[$relation][] = $name;
                } else {
                    $visible[$val] = true;
                    $hasVisible = true;
                }
            }
        }

        //隐藏属性数据
        foreach ($this->hidden as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    [$relation, $name] = explode('.', $val);
                    $hidden[$relation][] = $name;
                } else {
                    $hidden[$val] = true;
                }
            }
        }

        //追加属性
        foreach ($this->append as $key => $name) {
            $this->appendAttrToArray($item, $key, $name, $visible, $hidden);
        }

        $data = array_merge($this->data, $this->relation);

        foreach ($this->data as $key => $val) {
            if ($val instanceof Model || $val instanceof ModelCollection) {
                if (isset($visible[$key]) && is_array($visible[$key])) {
                    $val->visible($this->visible[$key]);
                } elseif (isset($hidden[$key]) && is_array($hidden[$key])) {
                    $val->hidden($this->hidden[$key], true);
                }

                if (!isset($hidden[$key]) || $hidden[$key] !== true) {
                    $item[$key] = $val->toArray();
                }
            } elseif (isset($visible[$key])) {
                $item[$key] = $this->getAttr($key);
            } elseif (!isset($hidden[$key]) && !$hasVisible) {
                $item[$key] = $this->getAttr($key);
            }

            if (isset($this->mapping[$key])) {
                $mapName = $this->mapping[$key];
                $item[$mapName] = $item[$key];
                unset($item[$key]);
            }
        }

        if ($this->convertNameToCamel) {
            foreach ($item as $key => $val) {
                $name = Str::camel($key);
                if ($name != $key) {
                    $item[$name] = $val;
                    unset($item[$key]);
                }
            }
        }

        return $item;
    }

    /**
     * Note: 追加属性到数组中去
     * Date: 2023-05-22
     * Time: 10:42
     * @param array $item 数据
     * @param string|int $key 关联属性名|键
     * @param string|array $name 关联属性的属性
     * @param array $visible 显示属性
     * @param array $hidden 隐藏属性
     * @return void
     */
    protected function appendAttrToArray(array &$item, $key, $name, $visible, $hidden)
    {
        if (is_array($name)) {
            $relation = $this->getRelationWith($key, $hidden, $visible);

            $item[$name] = $relation ? $relation->append($name)->toArray() : [];
        } elseif (strpos($name, '.')) {
            [$key, $attr] = explode('.', $name);

            $relation = $this->getRelationWith($key, $hidden, $visible);

            $item[$name] = $relation ? $relation->append([$attr])->toArray() : [];
        } else {
            $value = $this->getAttr($name);
            $item[$name] = $value;

            $this->getBindAttrValue($name, $value, $item);
        }
    }

    /**
     * Note: 获取关联对象
     * Date: 2023-11-09
     * Time: 18:54
     * @param string $key 关联对象名
     * @param array $hidden 隐藏属性
     * @param array $visible 显示属性
     * @return Model
     */
    protected function getRelationWith(string $key, array $hidden, array $visible)
    {
        $relation = $this->getRelation($key, true);
        if ($relation) {
            if (isset($visible[$key])) {
                $relation->visible($visible[$key]);
            } elseif (isset($hidden[$key])) {
                $relation->hidden($hidden[$key]);
            }
        }

        return $relation;
    }

    /**
     * Note: 获取绑定的关联属性值
     * Date: 2023-05-22
     * Time: 10:54
     * @param string $name 关联属性
     * @param mixed $value 关联属性值
     * @param array $item 数据
     * @return void
     */
    protected function getBindAttrValue(string $name, $value, &$item)
    {
        $relation = $this->isRelationAttr($name);
        if (!$relation) {
            return false;
        }

        $modelRelation = $this->$relation();
        if ($modelRelation instanceof OneToOne) {
            $bindAttr = $modelRelation->getBindAttr();

            if (!empty($bindAttr)) {
                unset($item[$name]);
            }

            foreach ($bindAttr as $key => $attr) {
                $key = is_numeric($key) ? $attr : $key;

                if (isset($item[$key])) {
                    throw new DbException('bind attr has exists:' . $key);
                }

                $item[$key] = $value ? $value->getAttr($value) : null;
            }
        }
    }

    /**
     * Note: 转换当前模型数据为JSON
     * Date: 2023-05-22
     * Time: 11:51
     * @param integer $options json参数
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        $this->toArray();
    }

    /**
     * Note: 切换数据集为数据集对象
     * Date: 2023-05-22
     * Time: 11:58
     * @param array|Collection $collection 数据集
     * @param strin $resultSetType 数据集类
     * @return Collection
     */
    public function toCollection($collection, string $resultSetType = null)
    {
        $resultSetType = $resultSetType ?: $this->resultSetType;

        if ($resultSetType && strpos($resultSetType, '\\') !== false) {
            $collection = new $resultSetType($collection);
        } else {
            $collection = new ModelCollection($collection);
        }

        return $collection;
    }

    public function __toString()
    {
        return $this->toJson();
    }
}