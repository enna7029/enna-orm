<?php
declare(strict_types=1);

namespace Enna\Orm\Model\Concern;

use DateTime;

trait TimeStamp
{
    /**
     * 是否自动写入时间戳 如果写入的是字符串,则表示的是事件字段的类型
     * @var bool|string
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var bool|string
     */
    protected $createTime = 'create_time';

    /**
     * 修改时间字段
     * @var bool|string
     */
    protected $updateTime = 'update_time';

    /**
     * 时间字段显示格式
     * @var string
     */
    protected $dateFormat;

    /**
     * Note: 获取自动写入时间字段
     * Date: 2023-05-11
     * Time: 15:51
     * @return bool|string
     */
    public function getAutoWriteTimestamp()
    {
        return $this->autoWriteTimestamp;
    }

    /**
     * Note: 是否自动写入时间戳
     * Date: 2023-05-11
     * Time: 16:43
     * @param bool|string $auto
     * @return $this
     */
    public function isAutoWriteTimestamp($auto)
    {
        $this->autoWriteTimestamp = $this->checkTimeFieldType($auto);

        return $this;
    }

    /**
     * Note: 检查时间字段实际类型
     * Date: 2023-05-11
     * Time: 16:44
     * @param bool|string $type 类型
     * @return mixed
     */
    protected function checkTimeFieldType($type)
    {
        if ($type === true) {
            if (isset($this->type[$this->createTime])) {
                $type = $this->type[$this->createTime];
            } elseif (isset($this->schema[$this->createTime]) && in_array($this->schema[$this->createTime], 'datetime', 'timestamp', 'int', 'date')) {
                $type = $this->schema[$this->createTime];
            } else {
                $type = $this->getFieldType($this->createTime);
            }
        }

        return $type;
    }

    /**
     * Note: 设置时间格式化
     * Date: 2023-05-11
     * Time: 18:03
     * @param string|false $format 时间格式
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Note: 获取自动写入时间字段类型
     * Date: 2023-05-11
     * Time: 18:01
     * @return string|null
     */
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Note: 设置时间字段名称
     * Date: 2023-05-11
     * Time: 18:14
     * @param string $createTime
     * @param string $updateTime
     * @return $this
     */
    public function setTimeField(string $createTime, string $updateTime)
    {
        $this->createTime = $createTime;
        $this->updateTime = $updateTime;

        return $this;
    }

    /**
     * Note: 时间日期字段格式化
     * Date: 2023-05-15
     * Time: 18:26
     * @param mixed $format 格式化
     * @param mixed $time 日期表达式
     * @param bool $timestamp 日期表达式是否为时间戳
     * @return mixed
     */
    protected function formatDateTime($format, $time = 'now', bool $timestamp = false)
    {
        if (empty($time)) {
            return;
        }

        if ($format == false) {
            return $time;
        } elseif (strpos($time, '\\') !== false) {
            return new $format($time);
        }

        if ($time instanceof DateTime) {
            $dateTime = $time;
        } elseif ($timestamp) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int)$time);
        } else {
            $dateTime = new DateTime($time);
        }

        return $dateTime->format($format);
    }

    /**
     * Note: 获取时间字段值
     * Date: 2023-05-16
     * Time: 16:45
     * @param mixed $value 值
     * @return mixed
     */
    protected function getTimestampValue($value)
    {
        $type = $this->checkTimeFieldType($value);

        if (is_string($type) && in_array(strtolower($type), ['datetime', 'date', 'timestamp'])) {
            $value = $this->formatDateTime($this->dateFormat, $value);
        } else {
            $value = $this->formatDateTime($this->dateFormat, $value, true);
        }

        return $value;
    }

    /**
     * Note: 自动写入时间戳
     * Date: 2023-05-16
     * Time: 17:38
     * @return mixed
     */
    public function autoWriteTimestamp()
    {
        $type = $this->checkTimeFieldType($this->autoWriteTimestamp);

        return is_string($type) ? $this->getTimeTypeValue($type) : time();
    }

    /**
     * Note: 获取指定类型的时间字段值
     * Date: 2023-05-16
     * Time: 17:40
     * @param string $type
     */
    public function getTimeTypeValue(string $type)
    {
        $value = time();

        switch ($type) {
            case 'datetime':
            case 'date':
            case 'timestamp':
                $value = $this->formatDateTime('Y-m-d H:i:s');
                break;
            default:
                if (strpos($type, '\\') !== false) {
                    $obj = new $type();
                    if (method_exists($obj, '__toString')) {
                        $value = $obj->__toString();
                    }
                }
        }

        return $value;
    }
}