<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

use InvalidArgumentException;

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
     * Note: 设置数据字段获取器
     * Date: 2023-05-11
     * Time: 17:15
     * @param array|string $name 字段名
     * @param callable $callback 闭包获取器
     * @return $this
     */
    public function withAttribute($name, callable $callback = null)
    {

    }
}