<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

trait ModelEvent
{
    /**
     * Event对象
     * @var ojbect
     */
    protected static $event;

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * Note: 出触发事件
     * Date: 2023-03-17
     * Time: 11:40
     * @param string $event 事件名
     * @return bool
     */
    protected function trigger(string $event)
    {

    }
}