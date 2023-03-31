<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Orm\Facade\Db;
use PDO;
use PDOStatement;
use Throwable;
use Exception;
use Enna\Orm\Db\Exception\BindParamException;
use Enna\Orm\Db\Exception\PDOException;
use Enna\Orm\Db\Exception\DbEventException;
use Enna\Orm\Db\Exception\DbException;
use Enna\Orm\Model;
use Closure;

/**
 * 数据库连接基础类
 * @property PDO[] $links
 * @property PDO $linkID
 * @property PDO $linkRead
 * @property PDO $linkWrite
 */
class PDOConnection extends Connection
{
    const PARAM_FLOAT = 21;

    protected $config = [
        //数据库类型
        'type' => '',
        //服务器地址
        'hostname' => '',
        //数据库名
        'database' => '',
        //用户名
        'username' => '',
        //密码
        'password' => '',
        //端口
        'hostport' => '',
        //连接dsn
        'dsn' => '',
        //数据库连接参数
        'params' => '',
        //数据库编码:默认utf8
        'charset' => 'utf8mb4',
        //数据表前缀
        'prefix' => '',
        //数据库部署方式:0(单一服务器) 1(分布式服务器)
        'deploy' => 0,
        //数据库是否读写分离
        'rw_separate' => false,
        //读写分离后,主数据库数量
        'master_num' => 1,
        //指定从数据库序号
        'slave_no' => '',
        //模型写入后自动读取主数据库
        'read_master' => false,
        //是否严格检查字段是否存在
        'fields_strict' => true,
        //开启字段缓存
        'fields_cache' => false,
        //监听SQL
        'trigger_sql' => true,
        //Builder(解析)类
        'builder' => '',
        //Query(查询)类
        'query' => '',
        //是否需要断线重连
        'break_reconnect' => false,
        //断线标识字符
        'break_match_str' => [],
    ];

    /**
     * PDO操作实例
     * @var PDOStatement
     */
    protected $PDOStatement;

    /**
     * 当前SQL指令
     * @var string
     */
    protected $queryStr = '';

    /**
     * 事务指令数
     * @var int
     */
    protected $transTimes = 0;

    /**
     * 重连次数
     * @var int
     */
    protected $reConnectTimes = 0;

    /**
     * 查询结果类型
     * @var int
     */
    protected $fetchType = PDO::FETCH_ASSOC;

    /**
     * 字段属性大小写
     * @var int
     */
    protected $attrCase = PDO::CASE_LOWER;

    /**
     * 数据表信息
     * @var array
     */
    protected $info = [];

    /**
     * 查询开始时间
     * @var float
     */
    protected $queryStartTime;

    /**
     * 绑定参数
     * @var array
     */
    protected $bind = [];

    /**
     * PDO连接参数
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * 参数绑定类型映射
     * @var array
     */
    protected $bindType = [
        'str' => PDO::PARAM_STR,
        'string' => PDO::PARAM_STR,
        'int' => PDO::PARAM_INT,
        'integer' => PDO::PARAM_INT,
        'bool' => PDO::PARAM_BOOL,
        'boolean' => PDO::PARAM_BOOL,
        'float' => self::PARAM_FLOAT,
        'date' => PDO::PARAM_STR,
        'datetime' => PDO::PARAM_STR,
        'timestamp' => PDO::PARAM_STR,
    ];

    /**
     * 服务器断线标识字符
     * @var array
     */
    protected $breakMatchStr = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
    ];

    /**
     * Note: 获取当前连接器对应的Builer解析类
     * Date: 2023-03-21
     * Time: 17:05
     * @return string
     */
    public function getBuilderClass()
    {
        return $this->getConfig('builder') ?: 'Enna\\Orm\\Db\\Builder\\' . ucfirst($this->config['type']);
    }

    /**
     * Note: 获取当前连接器对应的Query查询类
     * Date: 2023-03-21
     * Time: 17:04
     * @return string
     */
    public function getQueryClass()
    {
        return $this->getConfig('query') ?: Query::class;
    }

    /**
     * Note: 解析PDO连接的dsn信息
     * Date: 2023-03-24
     * Time: 18:13
     * @param array $config 配置信息
     * @return string
     */
    abstract function parseDsn(array $config);

    /**
     * Note: 取得数据表的字段信息
     * Date: 2023-03-24
     * Time: 18:14
     * @param string $tableName 数据表名称
     * @return array
     */
    abstract function getFields(string $tableName);

    /**
     * Note: 取的数据库的表信息
     * Date: 2023-03-24
     * Time: 18:22
     * @param string $dbName 数据库名称
     * @return array
     */
    abstract function getTables(string $dbName = '');

    /**
     * Note: 获取数据表信息
     * Date: 2023-03-28
     * Time: 17:45
     * @param mixed $tableName 数据表名
     * @param string $fetch 获取信息类型
     * @return mixed
     */
    public function getTableInfo($tableName, string $fetch = '')
    {
        if (is_array($tableName)) {
            $tableName = key($tableName) ?: current($tableName);
        }

        if (strpos($tableName, ',') || strpos($tableName, ')')) {
            return [];
        }

        [$tableName] = explode(' ', $tableName);

        $info = $this->getSchemaInfo($tableName);

        return $fetch ? $info[$fetch] : $info;
    }

    /**
     * Note: 获取数据表主键
     * Date: 2023-03-28
     * Time: 17:57
     * @param mixed $tableName 数据表名
     * @return string|array
     */
    public function getPk($tableName)
    {
        return $this->getTableInfo($tableName, 'pk');
    }

    /**
     * Note: 获取数据表自增主键
     * Date: 2023-03-28
     * Time: 18:52
     * @param mixed $tableName 数据表名
     * @return array
     */
    public function getAutoInc($tableName)
    {
        return $this->getTableInfo($tableName, 'autoinc');
    }

    /**
     * Note: 获取数据表字段信息
     * Date: 2023-03-28
     * Time: 18:55
     * @param mixed $tableName 数据表名
     * @return array
     */
    public function getTableFields($tableName)
    {
        return $this->getTableInfo($tableName, 'fields');
    }

    /**
     * Note: 获取数据表字段类型
     * Date: 2023-03-28
     * Time: 18:59
     * @param mixed $tableName 数据表名
     * @param string $field 字段名
     * @return array|string
     */
    public function getFieldsType($tableName, string $field = null)
    {
        $result = $this->getTableInfo($tableName, 'type');

        if ($field && isset($result[$field])) {
            return $result[$field];
        }
        return $result;
    }

    /**
     * Note: 获取数据表绑定信息
     * Date: 2023-03-28
     * Time: 18:59
     * @param mixed $tableName 数据表名
     * @return array
     */
    public function getFieldsBind($tableName)
    {
        return $this->getTableInfo($tableName, 'bind');
    }

    /**
     * Note: 获取schema信息
     * Date: 2023-03-28
     * Time: 15:02
     * @param string $tableName 数据库表名称
     * @param bool $force 强制从数据库获取
     * @return array
     */
    public function getSchemaInfo(string $tableName, $force = false)
    {
        if (!strpos($tableName, '.')) {
            $schema = $this->getConfig('database') . '.' . $tableName;
        } else {
            $schema = $tableName;
        }

        if (!isset($this->info[$schema]) || $force) {
            $cacheKey = $this->getSchemaCacheKey($schema);
            $cacheField = $this->config['fields_cache'] && !empty($this->cache);

            if ($cacheField && !$force) {
                $info = $this->cache->get($cacheKey);
            }

            if (empty($info)) {
                $info = $this->getTableFieldsInfo($tableName);
                if ($cacheField) {
                    $this->cache->set($cacheKey, $info);
                }
            }

            $pk = $info['_pk'] ?? null;
            $autoinc = $info['_autoicnc'] ?? null;
            unset($info['_pk'], $info['_autoicnc']);

            $bind = [];
            foreach ($info as $name => $val) {
                $bind[$name] = $this->getFieldBindType($val);
            }

            $this->info[$schema] = [
                'fields' => array_keys($info),
                'type' => $info,
                'bind' => $bind,
                'pk' => $pk,
                'autoinc' => $autoinc,
            ];
        }

        return $this->info[$schema];
    }

    /**
     * Note: 获取数据表信息缓存key
     * Date: 2023-03-28
     * Time: 15:08
     * @param string $schema 数据表名称
     * @return string
     */
    protected function getSchemaCacheKey(string $schema)
    {
        return $this->getConfig('hostname') . ':' . $this->getConfig('hostport') . '@' . $schema;
    }

    /**
     * Note: 获取数据表字段信息
     * Date: 2023-03-28
     * Time: 15:22
     * @param string $tableName 数据表名
     * @return array
     */
    public function getTableFieldsInfo(string $tableName)
    {
        $fields = $this->getFields($tableName);

        $info = [];
        foreach ($fields as $key => $val) {
            $info[$key] = $this->getFieldType($val['type']);

            if (!empty($val['primary'])) {
                $pk[] = $key;
            }

            if (!empty($val['autoinc'])) {
                $autoinc = $key;
            }
        }

        if (isset($pk)) {
            $pk = count($pk) > 1 ? $pk : $pk[0];
            $info['_pk'] = $pk;
        }

        if (isset($autoinc)) {
            $info['_autoinc'] = $autoinc;
        }

        return $info;
    }

    /**
     * Note: 获取字段类型
     * Date: 2023-03-28
     * Time: 16:01
     * @param string $type 字段类型
     * @return string
     */
    protected function getFieldType(string $type)
    {
        if (strpos($type, 'set') === 0 || strpos($type, 'enum') === 0) {
            $result = 'string';
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $result = 'float';
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $result = 'int';
        } elseif (preg_match('/bool/is', $type)) {
            $result = 'bool';
        } elseif (strpos($type, 'timestamp') === 0) {
            $result = 'timestamp';
        } elseif (strpos($type, 'datetime') === 0) {
            $result = 'datetime';
        } elseif (strpos($type, 'date')) {
            $result = 'date';
        } else {
            $result = 'string';
        }

        return $result;
    }

    /**
     * Note: 获取字段绑定类型
     * Date: 2023-03-28
     * Time: 17:34
     * @param string $type 字段类型
     * @return int
     */
    public function getFieldBindType(string $type)
    {
        if (in_array($type, ['int', 'integer', 'str', 'string', 'bool', 'boolean', 'float', 'date', 'datetime', 'timestamp'])) {
            $bind = $this->bindType[$type];
        } elseif (strpos($type, 'set') === 0 || strpos($type, 'enum') === 0) {
            $bind = PDO::PARAM_STR;
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $bind = self::PARAM_FLOAT;
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $bind = PDO::PARAM_INT;
        } elseif (preg_match('/bool/is', $type)) {
            $bind = PDO::PARAM_BOOL;
        } else {
            $bind = PDO::PARAM_STR;
        }

        return $bind;
    }

    /**
     * Note: 对返回的数据表字段信息进行大小写转换
     * Date: 2023-03-28
     * Time: 15:51
     * @param array $info 数据表字段信息
     * @return array
     */
    public function fieldCase(array $info)
    {
        switch ($this->attrCase) {
            case PDO::CASE_LOWER:
                $info = array_change_key_case($info);
                break;
            case PDO::CASE_UPPER:
                $info = array_change_key_case($info, CASE_UPPER);
                break;
            case PDO::CASE_NATURAL:
            default:
        }

        return $info;
    }

    /**
     * Note: 初始化数据库连接
     * Date: 2023-03-25
     * Time: 10:57
     * @param bool $master 是否主数据库
     * @return void
     */
    protected function initConnect(bool $master = true)
    {
        if (!empty($this->config['deploy'])) { //分布式连接
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }
                $this->linkID = $this->linkWrite; //写连接
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }
                $this->linkID = $this->linkRead; //读连接
            }
        } elseif (!$this->linkID) { //单连接
            $this->linkID = $this->connect();
        }
    }

    /**
     * Note: 连接分布式数据库
     * Date: 2023-03-25
     * Time: 11:44
     * @param bool $master 是否主数据库
     * @return PDO
     */
    protected function multiConnect(bool $master = false)
    {
        $config = [];

        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $config[$name] = is_string($this->config[$name]) ? explode(',', $this->config[$name]) : $this->config[$name];
        }

        $master_no = floor(mt_rand(0, $this->config['master_num'] - 1));

        if ($this->config['rw_separate']) { //读写分离
            if ($master) {
                $connect_no = $master_no;
            } elseif (is_numeric($this->config['slave_no'])) {
                $connect_no = $this->config['slave_no'];
            } else {
                $connect_no = floor(mt_rand($this->config['master_num'], count($config['hostname']) - 1));
            }
        } else { //不读写分离
            $connect_no = floor(mt_rand(0, count($config['hostname']) - 1));
        }

        //需要连接的数据库配置
        $dbConfig = [];
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
            $dbConfig[$name] = $config[$name][$connect_no] ?? $config[$name][0];
        }

        //当连接的数据库失败时,需要连接的主数据库
        $dbMaster = false;
        if ($connect_no != $master_no) {
            $dbMaster = [];
            foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset'] as $name) {
                $dbMaster[$name] = $config[$name][$master_no] ?? $config[$name][0];
            }
        }

        return $this->connect($dbConfig, $connect_no, $connect_no == $master_no ? false : $dbMaster);
    }

    /**
     * Note: 连接数据库
     * Date: 2023-03-24
     * Time: 18:34
     * @param array $config 配置
     * @param int $linkNum 连接序号
     * @param array|bool $autoConnection 是否主动连接到主数据库
     * @return PDO
     * @throws \PDOException
     */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false)
    {
        if (isset($this->links[$linkNum])) {
            return $this->links[$linkNum];
        }

        if (empty($config)) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }

        if (isset($config['params']) && is_array($config['params'])) {
            $params = $config['params'];
        } else {
            $params = $this->params;
        }

        $this->attrCase = $params[PDO::ATTR_CASE];

        if (!empty($config['break_match_str'])) {
            $this->breakMatchStr = array_merge($this->breakMatchStr, (array)$config['break_match_str']);
        }

        try {
            if (empty($config['dsn'])) {
                $config['dsn'] = $this->parseDsn($config);
            }

            $startTime = microtime(true);

            $this->links[$linkNum] = $this->createPdo($config['dsn'], $config['username'], $config['password'], $params);

            if (!empty($config['trigger_sql'])) {
                $this->trigger('CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']);
            }

            return $this->links[$linkNum];
        } catch (\PDOException $e) {
            if ($autoConnection) {
                $this->db->log($e->getMessage(), 'error');
                return $this->connect($autoConnection, $linkNum);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Note: 创建PDO数据库连接的实例
     * Date: 2023-03-25
     * Time: 10:54
     * @param $dsn
     * @param $username
     * @param $password
     * @param $params
     * @return PDO
     */
    protected function createPdo($dsn, $username, $password, $params)
    {
        return new PDO($dsn, $username, $params, $params);
    }

    /**
     * Note: 获取PDO对象
     * Date: 2023-03-29
     * Time: 14:54
     * @return PDO|false
     */
    public function getPdo()
    {
        if (!$this->linkID) {
            return false;
        }

        return $this->linkID;
    }

    /**
     * Note: 获取PDOStatement对象
     * Date: 2023-03-25
     * Time: 17:03
     * @param string $sql sql字符串
     * @param array $bind 参数绑定
     * @param bool $master 是否主数据库
     * @param bool $procedure 是否存储过程
     * @return PDOStatement
     * @throws DbException
     */
    public function getPDOStatement(string $sql, array $bind = [], bool $master = false, bool $procedure = false)
    {
        try {
            //初始化连接
            $this->initConnect($master);

            //初始化参数
            $this->queryStr = $sql;
            $this->bind = $bind;
            $this->db->getQueryTimes();
            $this->queryStartTime = microtime(true);

            //预处理
            $this->PDOStatement = $this->linkID->prepare($sql);

            //绑定到参数
            if ($procedure) {
                $this->bindParam($bind);
            } else {
                $this->bindValue($bind);
            }

            //执行
            $this->PDOStatement->execute();

            //SQL监控
            if (!empty($this->config['trigger_sql'])) {
                $this->trigger('', $master);
            }

            //重连
            $this->reConnectTimes = 0;

            return $this->PDOStatement;
        } catch (Throwable | Exception $e) {
            if ($this->transTimes > 0) {
                if ($this->isBreak($e)) {
                    $this->transTimes = 0;
                }
            } else {
                if ($this->reConnectTimes < 4 && $this->isBreak($e)) {
                    ++$this->reConnectTimes;
                    return $this->close()->getPDOStatement($sql, $bind, $master, $procedure);
                }
            }

            if ($e instanceof \PDOException) {
                throw new PDOException($e, $this->config, $this->getLastSql());
            } else {
                throw $e;
            }
        }
    }

    /**
     * Note: 参数绑定
     * Date: 2023-03-28
     * Time: 10:30
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    public function bindValue(array $bind)
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            if (is_array($val)) {
                if ($val[1] == PDO::PARAM_INT && $val[0] === '') {
                    $val[0] = 0;
                } elseif ($val[1] == self::PARAM_FLOAT) {
                    $val[0] = is_string($val[0]) ? (float)$val[0] : $val[0];
                    $val[1] = PDO::PARAM_STR;
                }
                $result = $this->PDOStatement->bindValue($param, $val[0], $val[1]);
            } else {
                $result = $this->PDOStatement->bindValue($param, $val);
            }

            if (!$result) {
                throw new BindParamException(
                    'Error occurred when binding parameters ' . $param,
                    $this->config,
                    $this->getLastSql(),
                    $bind
                );
            }
        }
    }

    /**
     * Note: 存储过程的输入输出参数绑定
     * Date: 2023-03-27
     * Time: 18:09
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindParam(array $bind)
    {
        foreach ($bind as $key => $val) {
            $param = is_numeric($key) ? $key + 1 : ':' . $key;

            if (is_array($val)) {
                array_unshift($val, $param);
                $result = call_user_func_array([$this->PDOStatement, 'bindParam'], $val);
            } else {
                $result = $this->PDOStatement->bindValue($param, $val);
            }

            if (!$result) {
                if ($val) {
                    $param = array_shift($val);
                }

                throw new BindParamException(
                    'Error occurred when binding parameters ' . $param,
                    $this->config,
                    $this->getLastSql(),
                    $bind
                );
            }
        }
    }

    /**
     * Note: 获取最近一次查询的SQL语句
     * Date: 2023-03-28
     * Time: 10:06
     * @return string
     */
    public function getLastSql()
    {
        return $this->getRealSql($this->queryStr, $this->bind);
    }

    /**
     * Note: 根据参数绑定组装出真实的SQL语句
     * Date: 2023-03-28
     * Time: 10:08
     * @param string $sql 带参数绑定的SQL语句
     * @param array $bind 参数绑定列表
     * @return string
     */
    public function getRealSql(string $sql, array $bind = [])
    {
        foreach ($sql as $key => $val) {
            $value = strval(is_array($val) ? $val[0] : $val);
            $type = is_array($val) ? $val[1] : PDO::PARAM_STR;

            if (PDO::PARAM_STR == $type || self::PARAM_FLOAT == $type) {
                $value = '\'' . addcslashes($value) . '\'';
            } elseif (PDO::PARAM_INT == $type && $value === '') {
                $value = 0;
            }

            $sql = is_numeric($key) ?
                substr_replace($sql, $value, strpos($sql, '?'), 1) :
                substr_replace($sql, $value, strpos($sql, ':' . $key), strlen(':' . $key));
        }

        return rtrim($sql);
    }

    /**
     * Note: 是否断线
     * Date: 2023-03-28
     * Time: 11:28
     * @param \PDOException|\Exception $e 异常对象
     * @return bool
     */
    protected function isBreak($e)
    {
        if (!$this->config['break_reconnect']) {
            return false;
        }

        $error = $e->getMessage();

        foreach ($this->breakMatchStr as $msg) {
            if (stripos($msg, $error) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Note: 关闭数据库连接
     * Date: 2023-03-28
     * Time: 11:36
     * @return $this
     */
    public function close()
    {
        $this->linkID = null;
        $this->linkWrite = null;
        $this->linkRead = null;
        $this->links = [];
        $this->transTimes = 0;

        $this->free();

        return $this;
    }

    /**
     * Note: 释放查询结果
     * Date: 2023-03-28
     * Time: 11:38
     * @return void
     */
    public function free()
    {
        $this->PDOStatement = null;
    }

    /**
     * Note: 执行语句:查询并返回数据集
     * Date: 2023-03-29
     * Time: 15:29
     * @param string $sql SQL指令
     * @param array $bind 参数绑定
     * @param bool $master 是否主数据库
     * @return array
     * @throws DbException
     */
    public function query(string $sql, array $bind = [], bool $master = false)
    {
        return $this->pdoQuery($this->newQuery(), $sql, $bind, $master);
    }

    /**
     * Note: 执行语句:更新和写入
     * Date: 2023-03-29
     * Time: 15:30
     * @param string $sql SQL指令
     * @param array $bind 参数绑定
     * @return int
     * @throws DbException
     */
    public function execute(string $sql, array $bind = [])
    {
        return $this->pdoExecute($this->newQuery(), $sql, $bind, true);
    }

    /**
     * Note: 视图查询
     * Date: 2023-03-29
     * Time: 14:45
     * @param mixed ...$args
     * @return BaseQuery
     */
    public function view(...$args)
    {
        return $this->newQuery()->view(...$args);
    }

    /**
     * Note: 执行查询:使用生成器返回数据
     * Date: 2023-03-29
     * Time: 15:00
     * @param BaseQuery $query 查询对象
     * @param string $sql SQL指令
     * @param array $bind 参数绑定
     * @param Model|null $model 模型对象实例
     * @param null $condition 查询条件
     * @return \Generator
     * @throws DbException
     */
    public function getCursor(BaseQuery $query, string $sql, array $bind = [], $model = null, $condition = null)
    {
        $this->queryPDOStatement($query, $sql, $bind);

        while ($result = $this->PDOStatement->fetch($this->fetchType)) {
            if ($model) {
                yield $model->newInstance($result, $condition);
            } else {
                yield $result;
            }
        }
    }

    /**
     * Note: 返回PDOStatement对象
     * Date: 2023-03-30
     * Time: 10:35
     * @param BaseQuery $query 查询对象
     * @return PDOStatement
     * @throws DbException
     */
    public function pdo(BaseQuery $query)
    {
        $bind = $query->getBind();

        $sql = $this->builder->select($query);

        return $this->queryPDOStatement($query, $sql, $bind);
    }

    /**
     * Note: 执行查询
     * Date: 2023-03-29
     * Time: 15:39
     * @param BaseQuery $query 查询对象
     * @param mixed $sql SQL指令
     * @param array $bind 参数绑定
     * @param bool $master 主库读取
     * @return array
     * @return DbException
     */
    protected function pdoQuery(BaseQuery $query, $sql, array $bind = [], bool $master = null)
    {
        //分析表达式
        $query->parseOptions();

        //查看缓存
        if ($query->getOptions('cache')) {
            $cacheItem = $this->parseCache($query, $query->getOptions('cache'));
            $key = $cacheItem->getKey();

            $data = $this->cache->get($key);
            if ($data !== null) {
                return $data;
            }
        }

        //执行SQL
        if ($sql instanceof Closure) {
            $sql = $sql($query);
            $bind = $query->getBind();
        }

        //是否主数据库读取
        if (!isset($master)) {
            $master = $query->getOptions('master') ? true : false;
        }

        //是否是存储过程
        $procedure = $query->getOptions('procedure') ? true : in_array(substr(strtolower(trim($sql)), 0, 4), ['call', 'exec']);

        //获取数据
        $this->getPDOStatement($sql, $bind, $master, $procedure);
        $resultSet = $this->getResult($procedure);

        //缓存数据
        if (isset($cacheItem) && $resultSet) {
            $cacheItem->set($resultSet);
            $this->cacheData($cacheItem);
        }

        return $resultSet;
    }

    /**
     * Note: 获取数据集数组
     * Date: 2023-03-29
     * Time: 17:46
     * @param bool $procedure 是否存储过程
     * @return array
     */
    protected function getResult(bool $procedure = false)
    {
        if ($procedure) {
            return $this->procedure();
        }

        $result = $this->PDOStatement->fetchAll($this->fetchType);

        $this->numRows = count($result);

        return $result;
    }

    /**
     * Note: 获取存储过程数据集
     * Date: 2023-03-29
     * Time: 17:58
     * @return array
     */
    protected function procedure()
    {
        $item = [];

        do {
            $result = $this->getResult();
            if (!empty($result)) {
                $item[] = $result;
            }
        } while ($this->PDOStatement->nextRowset());

        $this->numRows = count($item);

        return $item;
    }

    /**
     * Note: 执行更新或写入
     * Date: 2023-03-30
     * Time: 10:07
     * @param BaseQuery $query 查询对象
     * @param string $sql SQL指令
     * @param array $bind 参数绑定
     * @param bool $origin 是否原生查询
     * @return int
     * @throws DbException
     */
    public function pdoExecute(BaseQuery $query, string $sql, array $bind = [], bool $origin = false)
    {
        if ($origin) {
            $query->parseOptions();
        }

        $this->queryPDOStatement($query->master(true), $sql, $bind);
        if (!$origin && !empty($this->config['deploy']) && !empty($this->config['read_master'])) {
            $this->readMaster = true;
        }

        $this->numRows = $this->PDOStatement->rowCount();

        if ($query->getOptions('cache')) {
            $cacheItem = $this->parseCache($query, $query->getOptions('cache'));
            $key = $cacheItem->getKey();
            $tag = $cacheItem->getTag();

            if (!empty($key) && $this->cache->has($key)) {
                $this->cache->delete($key);
            }
            if (!empty($tag) && method_exists($this->cache, 'tag')) {
                $this->cache->tag($tag)->clear();
            }
        }

        return $this->numRows;
    }

    /**
     * Note: 获取PDOStatement对象
     * Date: 2023-03-29
     * Time: 15:09
     * @param BaseQuery $query 查询对象
     * @param string $sql SQL指令
     * @param array $bind 参数绑定
     * @return PDOStatement
     * @throws DbException
     */
    protected function queryPDOStatement(BaseQuery $query, string $sql, array $bind = [])
    {
        $options = $query->getOptions();
        $master = !empty($options['master']) ? true : false;
        $procedure = !empty($options['procedure']) ? true : in_array(strtolower(substr(trim($sql), 0, 4)), ['call', 'exec']);

        return $this->getPDOStatement($sql, $bind, $master, $procedure);
    }

    /**
     * Note: 查找单条记录
     * Date: 2023-03-30
     * Time: 10:38
     * @param BaseQuery $query 查询对象
     * @return array
     * @throws DbException
     */
    public function find(BaseQuery $query)
    {
        try {
            $this->db->trigger('before_find', $query);
        } catch (DbEventException $e) {
            return [];
        }

        $resultSet = $this->pdoQuery($query, function ($query) {
            return $this->builder->select($query, true);
        });
    }

    /**
     * Note: 使用游标查询记录
     * Date: 2023-03-31
     * Time: 14:31
     * @param BaseQuery $query 查询对象
     * @return \Generator
     */
    public function cursor(BaseQuery $query)
    {
        $options = $query->parseOptions();

        $sql = $this->builder->select();

        $condition = $options['where']['AND'] ?? null;

        return $this->getCursor($query, $sql, $query->getBind(), $query->getModel(), $condition);
    }

    /**
     * Note: 查找记录
     * Date: 2023-03-31
     * Time: 14:39
     * @param BaseQuery $query 查询对象
     * @return array|void
     * @throws DbException
     */
    public function select(BaseQuery $query)
    {
        try {
            $this->db->trigger('before_select', $query);
        } catch (DbEventException $e) {
            return [];
        }

        return $this->pdoQuery($query, function ($query) {
            $this->builder->select($query);
        });
    }

    /**
     * Note: 插入记录
     * Date: 2023-03-31
     * Time: 14:45
     * @param BaseQuery $query 查询对象
     * @param bool $getLastInsId 返回自增主键
     * @return mixed|void
     */
    public function insert(BaseQuery $query, bool $getLastInsId = false)
    {
        $options = $query->parseOptions();

        $sql = $this->builder->insert($query);

        $result = $sql == '' ? 0 : $this->pdoExecute($query, $sql, $query->getBind());
        if ($result) {
            $sequence = $options['sequence'] ?? null;
            $lastInsId = $this->getLastInsID($query, $sequence);

            $this->trigger('after_insert', $query);

            if ($getLastInsId && $lastInsId) {
                return $lastInsId;
            }
        }

        return $result;
    }

    /**
     * Note: 批量插入记录
     * Date: 2023-03-31
     * Time: 16:00
     * @param BaseQuery $query 查询对象
     * @param array $dataSet 数据集
     * @param int $limit 每次写入数据限制
     * @return int
     * @throws \Exception
     * @throws \Throwable
     */
    public function insertAll(BaseQuery $query, array $dataSet = [], int $limit = 0)
    {
        if (!is_array($dataSet)) {
            return 0;
        }

        $options = $query->parseOptions();
        $replace = !empty($options['replace']);

        if ($limit == 0 && $limit >= 5000) {
            $limit = 1000;
        }

        if ($limit) {
            $this->startTrans();

            try {
                $count = 0;
                $array = array_chunk($dataSet, $limit, true);

                foreach ($array as $item) {
                    $sql = $this->builder->insertAll($query, $item, $replace);
                    $count += $this->pdoExecute($query, $sql, $query->getBind());
                }

                $this->commit();
            } catch (Exception | Throwable $e) {
                $this->rollback();
                throw $e;
            }

            return $count;
        }

        $sql = $this->builder->insertAll($query, $dataSet, $replace);
        return $this->pdoExecute($query, $sql, $query->getBind());
    }

    public function selectInsert(BaseQuery $query)
    {

    }

    public function update(BaseQuery $query)
    {

    }

    public function delete(BaseQuery $query)
    {

    }

    public function value(BaseQuery $query)
    {

    }

    public function aggregate(BaseQuery $query)
    {

    }

    public function column(BaseQuery $query)
    {

    }

    public function transaction(callable $callback)
    {

    }

    /**
     * Note: 启动事务
     * Date: 2023-03-31
     * Time: 16:45
     * @return void
     * @throws \PDOException
     * @throws \Exception
     */
    public function startTrans()
    {
        try {
            $this->initConnect(true);

            ++$this->transTimes;

            if ($this->transTimes == 1) {
                $this->linkID->beginTransaction();
            } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
                $this->linkID->exec(
                    $this->parseSavepoint('trans' . $this->transTimes)
                );
            }

            $this->reConnectTimes = 0;
        } catch (\Throwable | \Exception $e) {
            if ($this->transTimes === 1 && $this->reConnectTimes < 4 && $this->isBreak($e)) {
                --$this->transTimes;
                ++$this->reConnectTimes;
                $this->close()->startTrans();
            } else {
                if ($this->isBreak($e)) {
                    $this->transTimes = 0;
                }
                throw $e;
            }
        }
    }

    /**
     * Note: 事务提交
     * Date: 2023-03-31
     * Time: 18:40
     * @return void
     */
    public function commit()
    {
        $this->initConnect(true);

        if ($this->transTimes == 1) {
            $this->linkID->commit();
        }

        --$this->transTimes;
    }

    /**
     * Note: 事务回滚
     * Date: 2023-03-31
     * Time: 18:41
     * @return void
     * @throws \PDOException
     */
    public function rollback()
    {
        $this->initConnect(true);

        if ($this->transTimes == 1) {
            $this->linkID->rollBack();
        } elseif ($this->transTimes > 1 && $this->supportSavepoint()) {
            $this->linkID->exec(
                $this->parseSavepointRollBack('trans' . $this->transTimes)
            );
        }

        $this->transTimes = max(0, $this->transTimes - 1);
    }

    /**
     * Note: 是否支持事务嵌套
     * Date: 2023-03-31
     * Time: 17:44
     * @return bool
     */
    public function supportSavepoint()
    {
        return false;
    }

    /**
     * Note: 生成事务保存点SQL
     * Date: 2023-03-31
     * Time: 17:46
     * @param string $name 标识
     * @return string
     */
    public function parseSavepoint(string $name)
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * Note: 生成回滚到保存点的SQL
     * Date: 2023-03-31
     * Time: 18:59
     * @param string $name 标识
     * @return string
     */
    public function parseSavepointRollBack(string $name)
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }

    public function startTransXa()
    {

    }

    public function prepareXa()
    {

    }

    public function commitXa()
    {

    }

    public function rollbackXa()
    {

    }

    public function batchQuery()
    {

    }

    /**
     * Note: 获取最近插入的ID
     * Date: 2023-03-31
     * Time: 15:17
     * @param BaseQuery $query 查询对象
     * @param string $sequence 自增序列名
     * @return mixed
     */
    public function getLastInsID(BaseQuery $query, string $sequence = null)
    {
        try {
            $insertId = $this->linkID->lastInsertId($sequence);
        } catch (\Exception $e) {
            $insertId = '';
        }

        return $this->autoInsIDType($query, $insertId);
    }

    /**
     * Note: 对最近插入的ID类型进行识别转换
     * Date: 2023-03-31
     * Time: 15:28
     * @param BaseQuery $query 查询对象
     * @param string $insertId 自动ID
     * @return mixed
     */
    protected function autoInsIDType(BaseQuery $query, string $insertId)
    {
        $autoinc = $query->getAutoInc();

        if ($autoinc) {
            $type = $this->getFieldBindType($autoinc);

            if ($type == PDO::PARAM_INT) {
                $insertId = (int)$insertId;
            } elseif ($type == self::PARAM_FLOAT) {
                $insertId = (float)$insertId;
            } elseif ($type == PDO::PARAM_STR) {
                $insertId = (string)$insertId;
            }
        }

        return $insertId;
    }

    public function getError()
    {

    }
}