<?php
declare(strict_types=1);

namespace Enna\Orm\Facade;

class Facade
{
    /**
     * 是否始终创建新的对象实例
     * @var bool
     */
    protected static $alwaysNewInstance;

    /**
     * 实例化后的对象
     * @var object
     */
    protected static $instance;

    protected static function getFacadeClass()
    {
    }

    /**
     * Note: 创建Facade实例
     * Date: 2023-03-23
     * Time: 15:06
     * @param bool $newInstance 是否每次创建新的实例
     * @return object
     */
    protected static function createFacade(bool $newInstance = false)
    {
        $class = static::getFacadeClass() ?: 'Enna\Orn\DbManager';

        if (static::$alwaysNewInstance) {
            $newInstance = true;
        }

        if ($newInstance) {
            return new $class();
        }

        if (!self::$instance) {
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::createFacade(), $method], $params);
    }

}