<?php
declare(strict_types=1);

namespace Enna\Orm\Contract;

use Enna\Orm\Db\BaseQuery;
use Enna\Orm\DbManager;
use Enna\Orm\Facade\Db;
use Psr\SimpleCache\CacheInterface;

/**
 * Interface ConnectionInterface
 * @package Enna\Orm\Contract
 */
interface ConnectionInterface
{
    /**
     * Note: 获取当前连接器的查询(Query)类
     * Date: 2023-03-21
     * Time: 15:49
     * @return string
     */
    public function getQueryClass();

    /**
     * Note: 指定表名(需要前缀)
     * Date: 2023-03-21
     * Time: 15:50
     * @return BaseQuery
     */
    public function table();

    /**
     * Note: 指定表名(不需要前缀)
     * Date: 2023-03-21
     * Time: 15:51
     * @return BaseQuery
     */
    public function name();

    /**
     * Note: 连接数据库的方法
     * Date: 2023-03-21
     * Time: 15:53
     * @param array $config 连接参数
     * @param int $linkNum 连续序号
     * @return mixed
     */
    public function connect(array $config = [], $linkNum = 0);

    /**
     * Note: 设置当前数据库的Db对象
     * Date: 2023-03-21
     * Time: 15:54
     * @param DbManager $db
     * @return mixed
     */
    public function setDb(DbManager $db);

    /**
     * Note: 获取数据库的配置参数
     * Date: 2023-03-17
     * Time: 15:47
     * @param string $config
     * @return mixed
     */
    public function getConfig(string $config = '');

    /**
     * Note: 设置当前的缓存对象
     * Date: 2023-03-21
     * Time: 9:54
     * @param CacheInterface $cache
     * @return void
     */
    public function setCache(CacheInterface $cache);

    /**
     * 关闭数据库(或者重新连接)
     * Date: 2023-03-21
     * Time: 15:55
     * @return $this
     */
    public function close();

    /**
     * Note: 查找单条记录
     * Date: 2023-03-21
     * Time: 16:35
     * @param BaseQuery $query 查询对象
     * @return array
     */
    public function find(BaseQuery $query);

    /**
     * Note: 查找记录
     * Date: 2023-03-21
     * Time: 16:36
     * @param BaseQuery $query 查询对象
     * @return array
     */
    public function select(BaseQuery $query);

    /**
     * Note: 插入记录
     * Date: 2023-03-21
     * Time: 16:36
     * @param BaseQuery $query 查询对象
     * @param bool $getLastInsID 返回主键ID
     * @return mixed
     */
    public function insert(BaseQuery $query, bool $getLastInsID = false);

    /**
     * Note: 批量插入记录
     * Date: 2023-03-21
     * Time: 16:37
     * @param BaseQuery $query 查询对象
     * @param array $dataSet 数据集
     * @return int
     */
    public function insertAll(BaseQuery $query, array $dataSet = []);

    /**
     * Note: 更新记录
     * Date: 2023-03-21
     * Time: 16:41
     * @param BaseQuery $query 查询对象
     * @return int
     */
    public function update(BaseQuery $query);

    /**
     * Note: 删除记录
     * Date: 2023-03-21
     * Time: 16:42
     * @param BaseQuery $query 查询对象
     * @return int
     */
    public function delete(BaseQuery $query);

    /**
     * Note: 得到某个字段的值
     * Date: 2023-03-21
     * Time: 16:43
     * @param BaseQuery $query 查询对象
     * @param string $field 字段
     * @param mixed $default 默认值
     * @return mixed
     */
    public function value(BaseQuery $query, string $field, $default = null);

    /**
     * Note: 得到某个列的数组
     * Date: 2023-03-21
     * Time: 16:45
     * @param BaseQuery $query 查询对象
     * @param string|array $column 字段名,多个字段用逗号隔开
     * @param string $key 索引
     * @return mixed
     */
    public function column(BaseQuery $query, $column, string $key = '');

    /**
     * Note: 执行数据库事务
     * Date: 2023-03-21
     * Time: 16:46
     * @param callable $callback 回调函数
     * @return mixed
     */
    public function transaction(callable $callback);

    /**
     * Note: 启动事务
     * Date: 2023-03-21
     * Time: 16:49
     * @return void
     */
    public function startTrans();

    /**
     * Note: 提交事务
     * Date: 2023-03-21
     * Time: 16:49
     * @return void
     */
    public function commit();

    /**
     * Note: 回滚事务
     * Date: 2023-03-21
     * Time: 16:50
     * @return void
     */
    public function rollback();

    /**
     * Note: 获取最近一次查询的SQL语句
     * Date: 2023-03-21
     * Time: 16:50
     * @return string
     */
    public function getLastSql();
}