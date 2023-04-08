<?php
declare(strict_types=1);

namespace Enna\Orm;

use InvalidArgumentException;
use Enna\Framework\Middleware;
use Enna\Orm\Contract\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Enna\Orm\Db\Raw;

class DbManager
{
    /**
     * 数据库连接实例
     * @var array
     */
    protected $instance = [];

    /**
     * 数据库配置
     * @var object|array
     */
    protected $config;

    /**
     * SQL监听
     * @var array
     */
    protected $listen = [];

    /**
     * SQL日志
     * @var array
     */
    protected $dbLog = [];

    /**
     * 查询事件
     * @var object|array
     */
    protected $event;

    /**
     * 查询次数
     * @var int
     */
    protected $queryTimes = 0;

    /**
     * 查询缓存对象
     * @var CacheInterface
     */
    protected $cache;

    /**
     * 查询日志对象
     * @var LoggerInterface
     */
    protected $log;

    public function __construct()
    {
        Model::setDb($this);

        Model::maker(function (Model $model) {

        });
    }

    /**
     * Note: 初始化配置参数
     * Date: 2023-03-21
     * Time: 10:56
     * @param array $config 连接配置
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Note: 获取配置参数
     * Date: 2023-03-20
     * Time: 18:08
     * @param string $name 标识符
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $name = null, $default)
    {
        if ($name == '') {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * Note: 获取连接配置
     * Date: 2023-03-20
     * Time: 18:13
     * @param string $name
     * @return array
     */
    protected function getConnectionConfig(string $name)
    {
        $connections = $this->getConfig('connections');

        if (!isset($connections[$name])) {
            throw new InvalidArgumentException('未定义的db配置' . $name);
        }

        return $connections[$name];
    }

    /**
     * Note: 设置缓存对象
     * Date: 2023-03-20
     * Time: 18:26
     * @param CacheInterface $cache 缓存对象
     * @return void
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Note: 设置日志对象
     * Date: 2023-03-21
     * Time: 10:18
     * @param LoggerInterface $log 日志对象
     * @return void
     */
    public function setLog(LoggerInterface $log)
    {
        $this->log = $log;
    }

    /**
     * Note: 记录SQL日志
     * Date: 2023-03-21
     * Time: 10:43
     * @param string $log 日志信息
     * @param string $type 日志类型
     * @return void
     */
    public function log(string $log, string $type = 'sql')
    {
        if ($this->log) {
            $this->log->log($type, $log);
        } else {
            $this->dbLog[$type][] = $log;
        }
    }

    /**
     * Note: 获取SQL日志
     * Date: 2023-03-21
     * Time: 10:45
     * @param bool $clear 是否清空
     * @return array
     */
    public function getDbLog(bool $clear = false)
    {
        $logs = $this->dbLog;
        if ($clear) {
            $this->dbLog = [];
        }

        return $logs;
    }

    /**
     * Note: 监听SQL执行
     * Date: 2023-03-21
     * Time: 10:10
     * @param callable $callback 回调方法
     * @return void
     */
    public function listen(callable $callback)
    {
        $this->listen[] = $callback;
    }

    /**
     * Note: 获取监听SQL执行
     * Date: 2023-03-21
     * Time: 10:11
     * @return array
     */
    public function getListen()
    {
        return $this->listen;
    }

    /**
     * Note: 使用表达式设置数据
     * Date: 2023-03-21
     * Time: 11:08
     * @param string $value 表达式
     * @return Raw
     */
    public function raw(string $value)
    {
        return new Raw($value);
    }

    /**
     * Note: 更新查询次数
     * Date: 2023-03-21
     * Time: 11:12
     * @return void
     */
    public function updateQueryTimes()
    {
        $this->queryTimes++;
    }

    /**
     * Note: 重置查询次数
     * Date: 2023-03-21
     * Time: 11:12
     * @return void
     */
    public function clearQueryTimes()
    {
        $this->queryTimes = 0;
    }

    /**
     * Note: 获取查询次数
     * Date: 2023-03-21
     * Time: 11:13
     * @return int
     */
    public function getQueryTimes()
    {
        return $this->queryTimes;
    }

    /**
     * Note: 注册数据库回调事件
     * Date: 2023-03-21
     * Time: 11:19
     * @param string $event 事件名
     * @param callable $callback 回调方法
     * @return void
     */
    public function event(string $event, callable $callback)
    {
        $this->event[$event][] = $callback;
    }

    /**
     * Note: 触发事件
     * Date: 2023-03-21
     * Time: 11:23
     * @param string $event 事件名
     * @param mixed $params 参数
     * @return mixed
     */
    public function trigger(string $event, $params = null)
    {
        if (isset($this->event[$event])) {
            foreach ($this->event[$event] as $callback) {
                call_user_func_array($callback, $params);
            }
        }
    }

    /**
     * Note: 创建或切换数据库连接查询
     * Date: 2023-03-20
     * Time: 17:59
     * @param string|null $name 连接配置标识
     * @param bool $force 强制重新连接
     * @return ConnectionInterface
     */
    public function connect(string $name = null, bool $force = false)
    {
        return $this->instance($name, $force);
    }

    /**
     * Note: 创建数据库连接实例
     * Date: 2023-03-20
     * Time: 18:02
     * @param string|null $name 连接标识
     * @param bool $force 强制重新连接
     * @return ConnectionInterface
     */
    protected function instance(string $name = null, bool $force = false)
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if ($force || !isset($this->instance[$name])) {
            $this->instance[$name] = $this->createConnection($name);
        }

        return $this->instance[$name];
    }

    /**
     * Note: 创建连接
     * Date: 2023-03-20
     * Time: 18:06
     * @param string|null $name 连接标识
     * @return ConnectionInterface
     */
    protected function createConnection(string $name = null)
    {
        $config = $this->getConnectionConfig($name);

        $type = !empty($config['type']) ? $config['type'] : 'mysql';

        if (strpos($type, '\\') === false) {
            $class = $type;
        } else {
            $class = 'Enna\\Orm\\Db\\Connecter\\' . ucfirst($type);
        }

        /**
         * @var ConnectionInterface $connection
         */
        $connection = new $class($config);
        $connection->setDb($this);

        if ($this->cache) {
            $connection->setCache($this->cache);
        }

        return $connection;
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }

}