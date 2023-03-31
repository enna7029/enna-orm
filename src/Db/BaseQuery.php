<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Orm\Contract\ConnectionInterface;
use Enna\Orm\Db\Exception\DbException;

abstract class BaseQuery
{
    use Concern\ModelRelationQuery;

    /**
     * 当前数据库连接对象
     * @var Connection
     */
    protected $connection;

    /**
     * 当前数据库前缀
     * @var string
     */
    protected $prefix = '';

    /**
     * 当前数据库的表名(不包含前缀)
     * @var string
     */
    protected $name = '';

    /**
     * 当前数据库主键
     * @var string|array
     */
    protected $pk;

    /**
     * 当前数据库自增主键
     * @var string
     */
    protected $autoinc;

    /**
     * 当前查询参数
     * @var array
     */
    protected $options = [];

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        $this->prefix = $this->connection->getConfig('prefix');
    }

    /**
     * Note: 创建一个新的查询对象
     * Date: 2023-03-27
     * Time: 17:03
     * @return BaseQuery
     */
    public function newQuery()
    {

    }

    /**
     * Note: 获取当前的数据库Connection对象
     * Date: 2023-03-27
     * Time: 17:05
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Note: 指定当前操作的数据表名
     * Date: 2023-03-30
     * Time: 15:03
     * @param mixed $table 表名
     * @return $this
     */
    public function table($table)
    {
        if (is_string($table)) {
            if (strpos($table, ')')) {

            } elseif (strpos($table, ',') === false) {
                if (strpos($table, ' ')) {
                    [$item, $alias] = explode(' ', $table);
                    $table = [];
                    $this->alias([$item => $alias]);
                    $table[$item] = $alias;
                }
            } else {
                $tables = explode(',', $table);
                $table = [];
                foreach ($tables as $item) {
                    $item = trim($item);
                    if (strpos($item, ' ')) {
                        [$item, $alias] = explode(' ', $item);
                        $this->alias([$item => $alias]);
                        $table[$item] = $alias;
                    } else {
                        $table[] = $item;
                    }
                }
            }
        } elseif (is_array($table)) {
            $tables = $table;
            $table = [];

            foreach ($tables as $key => $value) {
                if (is_numeric($key)) {
                    $table[] = $value;
                } else {
                    $this->alias([$key => $value]);
                    $table[$key] = $value;
                }
            }
        }

        $this->options['table'] = $table;

        return $this;
    }

    /**
     * Note: 指定当前数据表名(不含前缀)
     * Date: 2023-03-27
     * Time: 17:06
     * @param string $name 不含前缀的数据库表名称
     * @return $this
     */
    public function name(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Note: 获取当前数据表名称
     * Date: 2023-03-27
     * Time: 17:08
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Note: 获取当前的查询参数
     * Date: 2023-03-22
     * Time: 15:21
     * @param string $name 参数名
     * @return mixed
     */
    public function getOptions(string $name = '')
    {
        if ($name === '') {
            return $this->options;
        }

        return $this->options[$name] ?? null;
    }

    /**
     * Note: 得到当前或指定名称的数据表名
     * Date: 2023-03-22
     * Time: 15:27
     * @param string $name 表名称
     * @return mixed
     */
    public function getTable(string $name = '')
    {
        if (empty($name) && isset($this->options['table'])) {
            return $this->options['table'];
        }

        $name = $name ?: $this->name;

        return $this->prefix . $name;
    }

    /**
     * Note: 设置查询缓存选项
     * Date: 2023-03-29
     * Time: 16:50
     * @param mixed $key 缓存key
     * @param int|\DateTime $expire 缓存有效期
     * @param string|array $tag 缓存标签
     * @return $this
     */
    public function cache($key, $expire, $tag)
    {
        if ($key === false || !$this->getConnection()->getCache()) {
            return $this;
        }

        if ($key instanceof \DateTimeInterface || $key instanceof \DateInterval || (is_int($key) && is_null($expire))) {
            $expire = $key;
            $key = true;
        }

        $this->options['cache'] = [$key, $expire, $tag];

        return $this;
    }

    /**
     * Note: 设置从主数据库读取
     * Date: 2023-03-29
     * Time: 17:33
     * @param bool $readMaster 是否从主数据库读取
     * @return $this
     */
    public function master(bool $readMaster = true)
    {
        $this->options['master'] = $readMaster;

        return $this;
    }

    /**
     * Note: 设置数据
     * Date: 2023-03-29
     * Time: 18:16
     * @param array $data 数据
     * @return $this
     */
    public function data(array $data)
    {
        $this->options['data'] = $data;

        return $this;
    }

    public function order()
    {

    }

    public function strict()
    {

    }

    public function lock()
    {

    }

    /**
     * Note: 指定数据表别名
     * Date: 2023-03-30
     * Time: 15:10
     * @param string|array $alias 别名
     * @return $this
     */
    public function alias($alias)
    {
        if (is_array($alias)) {
            $this->options['alias'] = $alias;
        } else {
            $table = $this->getTable();
            $this->options['alias'][$table] = $alias;
        }

        return $this;
    }

    public function field($field)
    {

    }

    /**
     * Note: 分析表达式
     * Date: 2023-03-29
     * Time: 16:20
     * @return array
     */
    public function parseOptions()
    {
        $options = $this->getOptions();

        //获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }

        //查询条件
        if (!isset($options['where'])) {
            $options['where'] = [];
        } elseif (isset($options['view'])) {
            $this->parseView($options);
        }

        if (!isset($options['strict'])) {
            $options['strict'] = $this->connection->getConfig('fields_strict');
        }

        foreach (['data', 'order', 'join', 'union'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = [];
            }
        }

        foreach (['master', 'lock', 'fetch_sql', 'array', 'distinct', 'procedure'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        foreach (['group', 'having', 'limit', 'force', 'comment', 'partition', 'duplicate', 'extra'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }

        if (isset($options['page'])) {
            [$page, $listRows] = $options['page'];
            $page = $page > 0 ? $page : 1;
            $listRows = $listRows ?: (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows * ($page - 1);
            $options['limit'] = $offset . ',' . $listRows;
        }

        $this->options = $options;

        return $options;
    }

    /**
     * Note: 把主键值转换为查询条件:支持复合主键
     * Date: 2023-03-31
     * Time: 11:34
     * @param array|string $data 主键数据
     * @return void
     * @throws DbException
     */
    public function parsePkWhere($data)
    {

    }

    public function __call(string $method, array $args)
    {
        if (strtolower(substr($method, 0, 5)) == 'getby') {

        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') {

        } elseif (strtolower(substr($method, 0, 7)) == 'whereor') {

        } elseif (strtolower(substr($method, 9, 5)) == 'where') {

        } else {
            throw new DbException('method not exist:' . static::class . '->' . $method);
        }
    }
}