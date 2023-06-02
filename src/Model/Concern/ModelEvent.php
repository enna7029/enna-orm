<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

use Enna\Framework\Helper\Str;
use Enna\Orm\Db\Exception\ModelEventException;

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
     * Note: 设置Event对象
     * Date: 2023-05-11
     * Time: 15:11
     * @param object $event Event对象
     * @return void
     */
    public static function setEvent($event)
    {
        self::$event = $event;
    }

    /**
     * Note: 当前操作的事件响应
     * Date: 2023-05-19
     * Time: 15:33
     * @param bool $event 是否需要事件响应
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;

        return $this;
    }

    /**
     * Note: 触发事件
     * Date: 2023-03-17
     * Time: 11:40
     * @param string $event 事件名
     * @return bool
     */
    protected function trigger(string $event)
    {
        if (!$this->withEvent) {
            return true;
        }

        $method = 'on' . Str::studly($event);

        try {
            if (method_exists(static::class, $method)) {
                $result = call_user_func([static::class, $method], $this);
            } elseif (is_object(self::$event) && method_exists(self::$event, 'trigger')) {
                $result = self::$event->trigger(static::class . '.' . $event, $this);
                $result = empty($result) ? true : end($result);
            } else {
                $result = true;
            }

            return $result === false ? false : true;
        } catch (ModelEventException $e) {
            return false;
        }
    }
}