<?php
declare(strice_types=1);

namespace Enna\Orm;

use Closure;
use JsonSerializable;
use ArrayAccess;
use Enna\Orm\Contract\Arrayable;
use Enna\Orm\Contract\Jsonable;
use Enna\Orm\Model\Concern\Attribute;
use Enna\Orm\Model\Concern\ModelEvent;
use Enna\Orm\Db\BaseQuery as Query;

abstract class Model implements JsonSerializable, ArrayAccess, Arrayable, Jsonable
{
    use Attribute;
    use ModelEvent;

    /**
     * 数据是否存在
     * @var bool
     */
    private $exists = false;

    /**
     * 更新条件
     * @var mixed
     */
    private $updateWhere;

    /**
     * Db对象
     * @var DbManager
     */
    protected static $db;

    /**
     * 模型名称
     * @var string
     */
    protected $name;

    /**
     * 数据库表名称
     * @var string
     */
    protected $table;

    /**
     * 数据库配置
     * @var string
     */
    protected $connection;

    /**
     * 数据表后缀
     * @var string
     */
    protected $suffix;

    /**
     * 服务注入(扩展服务)
     * @var array
     */
    protected static $maker = [];

    /**
     * 方法注入(扩展方法)
     * @var array
     */
    protected static $macro = [];

    /**
     * 已经初始化的模型
     * @var array
     */
    protected static $initialized = [];

    /**
     * 构造函数
     * Model constructor.
     * @param array $data 数据
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;

        if (!empty($data)) {
            foreach ($this->disuse as $key) {
                if (array_key_exists($key, $this->data)) {
                    unset($this->data[$key]);
                }
            }
        }

        $this->origin = $this->data;

        if (empty($this->name)) {
            $name = str_replace('\\', '/', static::class);
            $this->name = basename($name);
        }

        if (!empty(static::$maker)) {
            foreach (static::$maker as $maker) {
                call_user_func($maker, $this);
            }
        }

        $this->initialize();
    }

    /**
     * Note: 设置服务注入(扩展服务)
     * Date: 2023-03-16
     * Time: 18:09
     * @param Closure $maker
     * @return void
     */
    public static function maker(Closure $maker)
    {
        static::$maker[] = $maker;
    }

    /**
     * Note: 设置方法注入(扩展方法)
     * Date: 2023-03-16
     * Time: 18:59
     * @param string $method
     * @param Closure $closure
     * @return void
     */
    public static function macro(string $method, Closure $closure)
    {
        if (!isset(static::$macro[static::class])) {
            static::$macro[static::class] = [];
        }
        static::$macro[static::class][$method] = $closure;
    }

    /**
     * Note: 初始化模型
     * Date: 2023-03-16
     * Time: 18:18
     * @return void
     */
    private function initialize()
    {
        if (!isset(static::$initialized[static::class])) {
            static::$initialized[static::class] = true;
            static::init();
        }
    }

    /**
     * Note: 初始化
     * Date: 2023-03-16
     * Time: 18:23
     * @return void
     */
    protected static function init()
    {
    }

    /**
     * Note: 设置Db对象
     * Date: 2023-03-16
     * Time: 18:45
     * @param DbManager $db Db对象
     * @return void
     */
    public static function setDb(DbManager $db)
    {
        self::$db = $db;
    }

    /**
     * Note: 设置当前模型的数据库连接
     * Date: 2023-03-17
     * Time: 10:33
     * @param string $connection 数据库连接标识
     * @return $this
     */
    public function setConnection(string $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Note: 获取当前模型的数据库连接标识符
     * Date: 2023-03-17
     * Time: 11:43
     * @return string
     */
    public function getConnection()
    {
        return $this->connection ?: '';
    }

    /**
     * Note: 设置当前模型数据表的后缀
     * Date: 2023-03-17
     * Time: 10:35
     * @param string $suffix 数据表后缀
     * @return $this
     */
    public function setSuffix(string $suffix)
    {
        $this->suffix = $suffix;

        return $this;
    }

    /**
     * Note: 获取当前模型数据表的后缀
     * Date: 2023-03-17
     * Time: 11:44
     * @return string
     */
    public function getSuffix()
    {
        return $this->suffix ?: '';
    }

    /**
     * Note: 获取当前模型名称
     * Date: 2023-03-17
     * Time: 10:23
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Note: 设置数据是否存在
     * Date: 2023-03-17
     * Time: 11:29
     * @param bool $exists
     * @return $this
     */
    public function exist(bool $exists = true)
    {
        $this->exists = $exists;

        return $this;
    }

    /**
     * Note: 判断数据是否存在
     * Date: 2023-03-17
     * Time: 11:31
     * @return bool
     */
    public function isExist()
    {
        return $this->exists;
    }

    /**
     * Note: 设置模型的更新条件
     * Date: 2023-03-17
     * Time: 11:33
     * @param mixed $where 更新条件
     * @return void
     */
    public function setUpdateWhere($where)
    {
        $this->updateWhere = $where;
    }

    /**
     * Note: 创建新的模型实例
     * User: enna
     * Date: 2023-03-17
     * Time: 10:36
     * @param array $data 数据
     * @param mixed $where 更新条件
     * @return Model
     */
    public function newInstance(array $data = [], $where = null)
    {
        $model = new static($data);

        if ($this->connection) {
            $model->setConnection($this->connection);
        }

        if ($this->suffix) {
            $model->setSuffix($this->suffix);
        }

        if (empty($data)) {
            return $model;
        }

        $model->exist(true);

        $model->setUpdateWhere($where);

        $model->trigger('AfterRead');

        return $model;
    }

    /**
     * Note: 获取当前模型的数据库查询对象
     * User: enna
     * Date: 2023-03-17
     * Time: 9:20
     * @param array $scope 设置不使用的全局查询范围
     * @return Query
     */
    public function db()
    {
        //self::$db->connect();
    }

    /**
     * Note: 利用回调执行依赖注入
     * Date: 2023-03-16
     * Time: 18:47
     * @param $method
     * @param array $vars
     * @return false|mixed
     */
    public function invoke($method, array $vars = [])
    {
        return call_user_func_array($method instanceof Closure ? $method : [$this, $method], $vars);
    }

    public function __call($method, $args)
    {
        if (isset(static::$macro[static::class][$method])) {
            return call_user_func_array(static::$macro[static::class][$method], $args);
        }

        return call_user_func_array([$this->db(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        if (isset(static::$macro[static::class][$method])) {
            return call_user_func_array(static::$macro[static::class][$method], $args);
        }

        $model = new static();

        return call_user_func_array([$model->db(), $method], $args);
    }

    public function __destruct()
    {
    }
}