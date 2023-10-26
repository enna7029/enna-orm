<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

/**
 * SQL原始类
 * Class Raw
 * @package Enna\Orm\Db
 */
class Raw
{
    /**
     * 查询表达式
     * @var string
     */
    protected $value;

    /**
     * 参数绑定
     * @var array
     */
    protected $bind = [];

    public function __construct(string $value, array $bind = [])
    {
        $this->value = $value;
        $this->bind = $bind;
    }

    /**
     * Note: 获取表达式
     * Date: 2023-03-21
     * Time: 11:07
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Note: 获取参数绑定
     * Date: 2023-03-30
     * Time: 15:56
     * @return array
     */
    public function getBind()
    {
        return $this->bind;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}