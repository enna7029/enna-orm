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
     * Note: 设置时间格式化
     * Date: 2023-05-11
     * Time: 18:03
     * @param string|false $format 时间格式
     * @return $this
     */
    public function setDataFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
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
}