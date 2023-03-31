<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Orm\Contract\ConnectionInterface;
use Enna\Orm\DbManager;
use Psr\SimpleCache\CacheInterface;

abstract class Connection implements ConnectionInterface
{
    /**
     * Db对象
     * @var DbManager
     */
    protected $db;

    /**
     * 缓存对象
     * @var CacheInterface
     */
    protected $cache;

    /**
     * 数据库连接配置
     * @var array
     */
    protected $config = [];

    /**
     * Builder对象
     * @var Builder
     */
    protected $builder;

    /**
     * 查询开始时间
     * @var float
     */
    protected $queryStartTime;

    /**
     * 当前读连接ID
     * @var object
     */
    protected $linkRead;

    /**
     * 当前写连接ID
     * @var object
     */
    protected $linkWrite;

    /**
     * 当前的连接ID
     * @var object
     */
    protected $linkID;

    /**
     * 数据库连接ID:支持多个连接
     * @var array
     */
    protected $links = [];

    /**
     * 返回或影响记录数
     * @var int
     */
    protected $numRows = 0;

    /**
     * 是否读主数据库
     * @var bool
     */
    protected $readMaster = false;

    /**
     * 错误信息
     * @var string
     */
    protected $error = '';


    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $class = $this->getBuilderClass();
        $this->builder = new $class($this);
    }

    /**
     * Note: 获取解析器
     * Date: 2023-03-21
     * Time: 17:16
     * @return Builder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * Note: 创建查询对象
     * Date: 2023-03-21
     * Time: 15:45
     * @return BaseQuery
     */
    public function newQuery()
    {
        $class = $this->getQueryClass();

        /**
         * @var BaseQuery $query
         */
        $query = new $class($this);

        return $query;
    }

    /**
     * Note: 指定表名查询(包含后缀)
     * Date: 2023-03-21
     * Time: 17:18
     * @return BaseQuery
     */
    public function table()
    {
        return $this->newQuery()->table($table);
    }

    /**
     * Note: 不指定表明查询(不包含后缀)
     * Date: 2023-03-21
     * Time: 17:19
     * @return BaseQuery
     */
    public function name()
    {
        return $this->newQuery()->name($name);
    }

    /**
     * Note: 设置当前数据库的Db对象
     * Date: 2023-03-21
     * Time: 15:30
     * @param DbManager $db
     * @return void
     */
    public function setDb(DbManager $db)
    {
        $this->db = $db;
    }

    /**
     * Note: 设置当前缓存对象
     * Date: 2023-03-21
     * Time: 15:28
     * @param CacheInterface $cache
     * @return void
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Note: 获取缓存对象
     * Date: 2023-03-21
     * Time: 15:32
     * @return CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Note: 获取数据库配置参数
     * Date: 2023-03-21
     * Time: 15:34
     * @param string $config 配置名称
     * @return mixed
     */
    public function getConfig(string $config = '')
    {
        if ($config == '') {
            return $this->config;
        }

        return $this->config[$config] ?? null;
    }

    /**
     * Note: 触发SQL监听
     * Date: 2023-03-21
     * Time: 17:28
     * @param string $sql 执行的SQL语句
     * @param bool $master 主从标记
     * @return void
     */
    protected function trigger(string $sql = '', bool $master = false)
    {
        $listen = $this->db->getListen();
        if (empty($listen)) {
            $listen[] = function ($sql, $time, $master) {
                if (strpos($sql, 'CONNECT:')) {
                    $this->db->log($sql);
                    return;
                }

                if (is_bool($master)) {
                    $master = $master ? 'master|' : 'slave|';
                } else {
                    $master = '';
                }

                $this->db->log(sql . ' [ ' . $master . 'RunTime:' . $time . ' ] ');
            };
        }

        $runtime = number_format((microtime(true) - $this->queryStartTime), 6);
        $sql = $sql ?: $this->getLastSql();

        if (empty($this->config['deploy'])) {
            $master = null;
        }

        foreach ($listen as $callback) {
            if (is_callable($callback)) {
                $callback($sql, $runtime, $master);
            }
        }
    }

    /**
     * Note: 缓存数据
     * Date: 2023-03-21
     * Time: 18:44
     * @param CacheItem $cacheItem 缓存对象
     * @return void
     */
    protected function cacheData(CacheItem $cacheItem)
    {
        if ($cacheItem->getTag() && method_exists($this->cache, 'tag')) {
            $this->cache->tag($cacheItem->getTag())->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());
        } else {
            $this->cache->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());
        }
    }

    /**
     * Note: 分析缓存
     * Date: 2023-03-21
     * Time: 18:47
     * @param BaseQuery $query 查询对象
     * @param array $cache 缓存对象
     * @param string $method 查询方法
     * @return CacheItem
     */
    protected function parseCache(BaseQuery $query, array $cache, string $method = '')
    {
        [$key, $expire, $tag] = $cache;

        if ($key instanceof CacheItem) {
            $cacheItem = $key;
        } else {
            if ($key === true) {
                $key = $this->getCacheKey($query, $method);
            }
            $cacheItem = new CacheItem($key);
            $cacheItem->expire($expire);
            $cacheItem->tag($tag);
        }

        return $cacheItem;
    }

    /**
     * Note: 分析缓存key
     * Date: 2023-03-22
     * Time: 15:19
     * @param BaseQuery $query 查询对象
     * @param string $method 查询方法
     * @return string
     */
    protected function getCacheKey(BaseQuery $query, string $method = '')
    {
        if (!empty($query->getOptions('key')) && empty($method)) {
            $key = 'enna_' . $this->getConfig('database') . '.' . $query->getTable() . '|' . $query->getOptions('key');
        } else {
            $key = 'cache_key_123';
        }

        return $key;
    }

    /**
     * Note: 获取返回或者影响的记录数
     * Date: 2023-03-21
     * Time: 18:34
     * @return int
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    public function __destruct()
    {
        $this->close();
    }
}