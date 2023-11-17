<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Concern;

use Enna\Framework\Request;
use Enna\Orm\Model\Relation;
use InvalidArgumentException;
use Enna\Framework\Helper\Str;
use DateTime;
use Closure;

/**
 * 模型数据处理
 * Trait Attribute
 * @package Enna\Orm\Model\Concern
 */
trait Attribute
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 数据表字段信息
     * @var array
     */
    protected $schema = [];

    /**
     * 允许写入的字段
     * @var array
     */
    protected $field = [];

    /**
     * 字段自动类型转换
     * @var array
     */
    protected $type = [];

    /**
     * 数据表废弃字段
     * @var array
     */
    protected $disuse = [];

    /**
     * 数据表只读字段
     * @var array
     */
    protected $readonly = [];

    /**
     * 当前模型数据
     * @var array
     */
    private $data = [];

    /**
     * 原始数据
     * @var array
     */
    private $origin = [];

    /**
     * json类型字段
     * @var array
     */
    protected $json = [];

    /**
     * 设置json字段的类型
     * @var array
     */
    protected $jsonType = [];

    /**
     * json数据取出时,是否需要转换为数组
     * @var array
     */
    protected $jsonAssoc = false;

    /**
     * 是否严格字段大小写
     * @var bool
     */
    protected $strict = true;

    /**
     * 获取器数据
     * @var array
     */
    private $get = [];

    /**
     * 动态获取器
     * @var array
     */
    private $withAttr = [];

    /**
     * Note: 获取模型对象主键
     * Date: 2023-05-15
     * Time: 16:46
     * @return string
     */
    public function getPk()
    {
        return $this->pk;
    }

    /**
     * Note: 判断一个字段名是否为主键
     * Date: 2023-05-15
     * Time: 16:47
     * @param string $key 字段名
     * @return bool
     */
    public function isPk(string $key)
    {
        $pk = $this->getPk();

        if (is_string($pk) && $pk == $key) {
            return true;
        } elseif (is_array($pk) && in_array($key, $pk)) {
            return true;
        }

        return false;
    }

    /**
     * Note: 获取模型主键值
     * Date: 2023-05-15
     * Time: 16:49
     * @return mixed
     */
    public function getKey()
    {
        $pk = $this->getPk();

        if (is_string($pk) && array_key_exists($pk, $this->data)) {
            return $this->data[$pk];
        }

        return;
    }

    /**
     * Note: 设置允许写入的字段
     * Date: 2023-05-15
     * Time: 16:53
     * @param array $field 允许写入的字段
     * @return $this
     */
    public function allowField(array $field)
    {
        $this->field = $field;

        return $this;
    }

    /**
     * Note: 设置只读字段
     * Date: 2023-05-15
     * Time: 16:54
     * @param array $field 只读字段
     * @return $this
     */
    public function readOnly(array $field)
    {
        $this->readonly = $field;

        return $this;
    }

    /**
     * Note: 获取实际的字段名:将驼峰转换为下划线
     * Date: 2023-11-09
     * Time: 15:53
     * @param string $name
     * @return string
     */
    protected function getRealFieldName(string $name)
    {
        if ($this->convertNameToCamel || !$this->strict) {
            return Str::snake($name);
        }

        return $name;
    }

    /**
     * Note: 设置数据对象值
     * Date: 2023-05-15
     * Time: 17:05
     * @param array $data 数据
     * @param bool $set 是否调用修改器
     * @param array $allow 允许的字段名
     * @return $this
     */
    public function data(array $data, bool $set = false, array $allow = [])
    {
        //清空数据
        $this->data = [];

        //废弃字段
        foreach ($this->disuse as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }

        //允许字段
        if (!empty($allow)) {
            $result = [];
            foreach ($allow as $name) {
                if (isset($data[$name])) {
                    $result[$name] = $data[$name];
                }
            }

            $data = $result;
        }

        //设置数据
        if ($set) {
            $this->setAttrs($data);
        } else {
            $this->data = $data;
        }

        return $this;
    }

    /**
     * Note: 批量追加数据值
     * Date: 2023-05-16
     * Time: 9:38
     * @param array $data 数据
     * @param bool $set 是否调用修改器
     * @return void
     */
    public function appendData(array $data, bool $set = false)
    {
        if ($set) {
            $this->setAttrs($data);
        } else {
            $this->data = array_merge($this->data, $data);
        }

        return $this;
    }

    /**
     * Note: 刷新对象原始数据为当前数据
     * Date: 2023-11-09
     * Time: 15:57
     * @return $this
     */
    public function refreshOrigin()
    {
        $this->origin = $this->data;

        return $this;
    }

    /**
     * Note: 获取对象的原始数据
     * Date: 2023-05-16
     * Time: 9:57
     * @param string|null $name 字段名
     * @return mixed
     */
    public function getOrigin(string $name = null)
    {
        if (is_null($name)) {
            return $this->origin;
        }

        $fieldName = $this->getRealFieldName($name);

        return array_key_exists($fieldName, $this->origin) ? $this->origin[$fieldName] : null;
    }

    /**
     * Note: 获取当前对象的数据
     * Date: 2023-05-16
     * Time: 10:03
     * @param string|null $name 字段名
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getData(string $name = null)
    {
        if (is_null($name)) {
            return $this->data;
        }

        $fieldName = $this->getRealFieldName($name);

        if (array_key_exists($fieldName, $this->data)) {
            return $this->data[$fieldName];
        } elseif (array_key_exists($fieldName, $this->relation)) {
            return $this->relation[$fieldName];
        }

        throw new InvalidArgumentException('property not exists:' . static::class . '->' . $name);
    }

    /**
     * Note: 获取变化的数据:排除只读的数据
     * Date: 2023-05-16
     * Time: 13:52
     * @return array
     */
    public function getChangeData()
    {
        $data = $this->force ? $this->data : array_udiff_assoc($this->data, $this->origin, function ($a, $b) {
            if (empty($a) || empty($b) || $a !== $b) {
                return 1;
            }

            if (is_object($a) || $a !== $b) {
                return 1;
            } else {
                return 0;
            }
        });

        foreach ($this->readonly as $name) {
            if (array_key_exists($name, $data)) {
                unset($data[$name]);
            }
        }

        return $data;
    }

    /**
     * Note: 直接设置数据对象值
     * Date: 2023-05-15
     * Time: 18:39
     * @param string $name 属性
     * @param mixed $value 值
     * @return void
     */
    public function set(string $name, $value)
    {
        $fieldName = $this->getRealFieldName($name);

        $this->data[$fieldName] = $value;
        unset($this->get[$fieldName]);
    }

    /**
     * Note: 获取器
     * Date: 2023-05-16
     * Time: 14:10
     * @param string $name 属性名
     * @return mixed
     */
    public function getAttr(string $name)
    {
        try {
            $relation = false;
            $value = $this->getData($name);
        } catch (InvalidArgumentException $e) {
            $relation = $this->isRelationAttr($name);
            $value = null;
        }

        return $this->getValue($name, $value, $relation);
    }

    /**
     * Note: 获取经过获取器处理后的数据对象的值
     * Date: 2023-11-09
     * Time: 16:19
     * @param string $name 字段名
     * @param mixed $value 字段值
     * @param bool|string $relation 是否关联属性或者关联名
     * @return array|bool|float|int|mixed|\stdClass|null
     */
    protected function getValue(string $name, $value, $relation = false)
    {
        $name = $this->getRealFieldName($name);

        if (array_key_exists($name, $this->get)) {
            return $this->get[$name];
        }

        $method = 'get' . Str::studly($name) . 'Attr';
        if (isset($this->withAttr[$name])) {
            if ($relation) {
                $value = $this->getRelationValue($relation);
            }

            if (in_array($name, $this->json) && is_array($this->withAttr[$name])) {
                $value = $this->getJsonValue($name, $value);
            } else {
                $closure = $this->withAttr[$name];
                if ($closure instanceof Closure) {
                    $value = $closure($value, $this->data);
                }
            }
        } elseif (method_exists($this, $method)) {
            if ($relation) {
                $value = $this->getRelationValue($relation);
            }

            $value = $this->$method($value, $this->data);
        } elseif (isset($this->type[$name])) {
            $value = $this->readTransform($value, $this->type[$name]);
        } elseif ($this->autoWriteTimestamp && in_array($name, [$this->createTime, $this->updateTime])) {
            $value = $this->getTimestampValue($value);
        } elseif ($relation) {
            $value = $this->getRelationValue($relation);

            $this->relation[$name] = $value;
        }

        $this->get[$name] = $value;

        return $value;
    }

    /**
     * Note: 获取关联属性值
     * Date: 2023-05-16
     * Time: 16:20
     * @param string $relation 关联方法名
     * @return mixed
     */
    protected function getRelationValue(string $relation)
    {
        $modelRelation = $this->$relation();

        return $modelRelation instanceof Relation ? $this->getRelationData($modelRelation) : null;
    }

    /**
     * Note: 获取JSON字段属性值
     * Date: 2023-05-16
     * Time: 16:05
     * @param string $name 属性名
     * @param mixed $value JSON数据
     * @return mixed
     */
    protected function getJsonValue(string $name, $value)
    {
        if (is_numeric($value)) {
            return $value;
        }

        foreach ($this->withAttr[$name] as $key => $closure) {
            if ($this->jsonAssoc) {
                $value[$key] = $closure($value[$key], $this->data);
            } else {
                $value->$key = $closure($value->$key, $this->data);
            }
        }

        return $value;
    }

    /**
     * Note: 数据读取时类型转换
     * Date: 2023-05-16
     * Time: 16:33
     * @param mixed $value 值
     * @param string|array $type 类型
     * @return mixed
     */
    protected function readTransform($value, $type)
    {
        if (is_null($value)) {
            return;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (strpos($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        switch ($type) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                if (empty($param)) {
                    $value = (float)$value;
                } else {
                    $value = (float)number_format($value, (int)$param, '.', '');
                }
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'timestamp':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = $this->formatDateTime($format, $value, true);
                }
                break;
            case 'datetime':
                if (!is_null($value)) {
                    $format = !empty($param) ? $param : $this->dateFormat;
                    $value = $this->formatDateTime($format, $value);
                }
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
            case 'array':
                $value = empty($value) ? [] : json_decode($value, true);
                break;
            case 'object':
                $value = empty($value) ? new \stdClass() : json_decode($value);
                break;
            case 'serialize':
                try {
                    $value = unserialize($value);
                } catch (\Exception $e) {
                    $value = null;
                }
                break;
            default:
                if (false !== strpos($type, '\\')) {
                    $value = new $type($value);
                }
        }

        return $value;
    }

    /**
     * Note: 修改器:批量设置数据值
     * Date: 2023-05-15
     * Time: 17:17
     * @param array $data 数据
     * @return void
     */
    public function setAttrs(array $data)
    {
        foreach ($data as $name => $value) {
            $this->setAttr($name, $value, $data);
        }
    }

    /**
     * Note: 修改器:设置数据值
     * Date: 2023-05-15
     * Time: 17:29
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @param array $data 数据
     * @return void
     */
    public function setAttr(string $name, $value, array $data = [])
    {
        $name = $this->getRealFieldName($name);

        $method = 'set' . Str::studly($name) . 'Attr';

        if (method_exists($this, $method)) {
            $array = $this->data;

            $value = $this->$method($value, array_merge($this->data, $data));

            if (is_null($value) && $array !== $this->data) {
                return;
            }
        } elseif (isset($this->type[$name])) {
            $value = $this->writeTransform($value, $this->type[$name]);
        } elseif ($this->isRelationAttr($name)) {
            $this->relation[$name] = $value;
        } elseif ((array_key_exists($name, $this->origin) || empty($this->origin)) && is_object($value) && method_exists($value, '__toString')) {
            $value = $value->__toString();
        }

        $this->data[$name] = $value;
        unset($this->get[$name]);
    }

    /**
     * Note: 数据写入时,值类型的转换
     * Date: 2023-05-15
     * Time: 17:50
     * @param mixed $value 值
     * @param string|arary $type 类型
     * @return mixed
     */
    protected function writeTransform($value, $type)
    {
        if (is_null($value)) {
            return;
        }

        if ($value instanceof Raw) {
            return $value;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (strpos($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        switch ($type) {
            case 'integer':
                $value = (int)$value;
                break;
            case 'float':
                if (!isset($param)) {
                    $value = (float)$value;
                } else {
                    $value = (float)number_format($value, (int)$param, '.', '');
                }
                break;
            case 'boolean':
                $value = (bool)$value;
                break;
            case 'array':
                $value = (array)$value;
                break;
            case 'object':
                if (is_object($value)) {
                    $value = json_encode($value, JSONO_FORCE_OBJECT);
                }
                break;
            case 'serialize':
                $value = serialize($value);
                break;
            case 'json':
                $option = !empty($param) && isset($param) ? (int)$param : JSON_UNESCAPED_UNICODE;
                $value = json_encode($value, $option);
                break;
            case 'timestamp':
                if (!is_numeric($value)) {
                    $value = strtotime($value);
                }
                break;
            case 'datetime':
                $value = is_numeric($value) ? $value : strtotime($value);
                $value = $this->formatDateTime('Y-m-d H:i:s', $value, true);
                break;
            default:
                if (is_object($value) && strpos($value, '\\') !== false && method_exists($value, '__toString')) {
                    $value = $value->__toString();
                }
        }

        return $value;
    }

    /**
     * Note: 获取器:设置数据字段
     * Date: 2023-05-11
     * Time: 17:15
     * @param array|string $name 字段名
     * @param callable $callback 闭包获取器
     * @return $this
     */
    public function withAttr($name, callable $callback = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $val) {
                $this->withAttr($key, $val);
            }
        } else {
            $name = $this->getRealFieldName($name);

            if (strpos($name, '.')) {
                [$name, $key] = explode('.', $name);

                $this->withAttr[$name][$key] = $callback;
            } else {
                $this->withAttr[$name] = $callback;
            }
        }

        return $this;
    }
}