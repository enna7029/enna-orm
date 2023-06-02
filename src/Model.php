<?php
declare(strice_types=1);

namespace Enna\Orm;

use Closure;
use JsonSerializable;
use ArrayAccess;
use Enna\Orm\Contract\Arrayable;
use Enna\Orm\Contract\Jsonable;
use Enna\Orm\Db\BaseQuery as Query;

abstract class Model implements JsonSerializable, ArrayAccess, Arrayable, Jsonable
{
    use Model\Concern\Attribute;
    use Model\Concern\ModelEvent;
    use Model\Concern\TimeStamp;
    use Model\Concern\RelationShip;
    use Model\Concern\Conversion;
    //use Model\Concern\SoftDelete;

    /**
     * 数据是否存在
     * @var bool
     */
    private $exists = false;

    /**
     * 是否强制更新数据
     * @var bool
     */
    private $force = false;

    /**
     * 是否replace
     * @var bool
     */
    private $replace = false;

    /**
     * 更新条件
     * @var mixed
     */
    private $updateWhere;

    /**
     * 数据库配置
     * @var string
     */
    protected $connection;

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
     * 数据表后缀
     * @var string
     */
    protected $suffix;

    /**
     * 主键
     * @var string
     */
    protected $key;

    /**
     * 是否延迟保存
     * @var bool
     */
    private $lazySave = false;

    /**
     * 软删除默认字段值
     * @var mixed
     */
    protected $defaultSoftDelete;

    /**
     * 全局查询范围
     * @var array
     */
    protected $globalScope = [];

    /**
     * Db对象
     * @var DbManager
     */
    protected static $db;

    /**
     * 容器对象的依赖注入方法
     * @var callable
     */
    protected static $invoker;

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
     * Note: 设置容器对象的依赖注入方法
     * Date: 2023-05-11
     * Time: 15:29
     * @param callable $callable 依赖注入方法
     * @return void
     */
    public static function setInvoker(callable $callable)
    {
        self::$invoker = $callable;
    }

    /**
     * Note: 调用反射执行模型方法 支持参数绑定
     * Date: 2023-05-11
     * Time: 15:33
     * @param mixed $method 闭包或方法
     * @param array $vars 参数
     * @return mixed
     */
    public function invoke($method, array $vars = [])
    {
        if (self::$invoker) {
            $call = self::$invoker;
            return $call($method instanceof Closure ? $method : Closure::fromCallable([$this, $method]), $vars);
        }

        return call_user_func_array($method instanceof Closure ? $method : Closure::fromCallable([$this, $method]), $vars);
    }

    /**
     * Note: 获取当前模型的数据库查询对象
     * Date: 2023-03-17
     * Time: 9:20
     * @param array $scope 设置不使用的全局查询范围
     * @return Query
     */
    public function db(array $scope = [])
    {
        $query = self::$db->connect($this->connection)
            ->name($this->name . $this->suffix)
            ->pk($this->pk);

        if (!empty($this->table)) {
            $query->table($this->table . $this->suffix);
        }

        $query->model($this)
            ->json($this->json, $this->jsonAssoc)
            ->setFieldType(array_merge($this->schema, $this->jsonType));

        if (property_exists($this, 'withTrashed') && !$this->withTrashed) {
            $this->withNoTrashed($query);
        }

        if (is_array($scope)) {
            $globalScope = array_diff($this->globalScope, $scope);
            $query->scope($globalScope);
        }

        return $query;
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
     * Note: 设置不使用的全局查询
     * Date: 2023-05-20
     * Time: 11:45
     * @param array|null $scope 全局查询
     * @return Query
     */
    public static function withoutGlobalScope(array $scope = null)
    {
        $model = new static;

        return $model->db($scope);
    }

    /**
     * Note: 切换数据库连接
     * Date: 2023-05-16
     * Time: 10:10
     * @param string $connection 数据库连接标识
     * @return Model
     */
    public static function connect(string $connection)
    {
        $model = new static();
        $model->setConnection($connection);

        return $model;
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
     * Note: 设置后缀进行查询
     * Date: 2023-05-20
     * Time: 11:42
     * @param string $suffix 后缀
     * @return Model
     */
    public static function suffix(string $suffix)
    {
        $model = new static();
        $model->setSuffix($suffix);

        return $model;
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
     * Note: 判断模型是否为没空
     * Date: 2023-05-20
     * Time: 11:06
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->data);
    }

    /**
     * Note: 是否强制写入数据|是否强制删除数据
     * Date: 2023-05-19
     * Time: 15:27
     * @param bool $force
     * @return $this
     */
    public function force(bool $force = true)
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Note: 判断force
     * Date: 2023-05-19
     * Time: 15:28
     * @return bool
     */
    public function isForce()
    {
        return $this->force;
    }

    public function getWhere()
    {

    }

    /**
     * Note: 延迟保存当前数据独享
     * Date: 2023-05-20
     * Time: 9:41
     * @param array|bool $data 数据
     * @return void
     */
    public function lazySave($data = [])
    {
        if ($data == false) {
            $this->lazySave = false;
        } else {
            if (is_array($data)) {
                $this->setAttrs($data);
            }

            $this->lazySave = true;
        }
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

    public function save(array $data = [], string $sequence = null)
    {

    }

    public function delete()
    {

    }

    /**
     * Note: 反序列化操作
     * Date: 2023-05-15
     * Time: 15:17
     */
    public function __wakeup()
    {
        $this->initialize();
    }

    /**
     * Note: 修改器:不存在或不可访问的属性赋值时
     * Date: 2023-05-15
     * Time: 15:29
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @return void
     */
    public function __set($name, $value)
    {
        $this->setAttr($name, $value);
    }

    /**
     * Note: 获取器:访问不存在或不可访问的属性时
     * Date: 2023-05-15
     * Time: 15:34
     * @param string $name 属性名
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getAttr($name);
    }

    /**
     * Note: 调用isset或empty时访问
     * Date: 2023-05-15
     * Time: 16:12
     * @param string $name 属性名
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return !is_null($this->getAttr($name));
    }

    /**
     * Note: 销毁属性
     * Date: 2023-05-15
     * Time: 16:13
     * @param string $name 属性名
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->data[$name], $this->get[$name], $this->relation[$name]);
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttr($offset, $value);
    }

    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }

    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    /**
     * Note: 执行方法 1.注入的方法2.获取器3.查询构造器
     * Date: 2023-05-15
     * Time: 15:39
     * @param string $method 方法名称
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (isset(static::$macro[static::class][$method])) {
            return call_user_func_array(static::$macro[static::class][$method], $args);
        }

        if (strtolower($method) == 'withattr') {
            return call_user_func_array([$this, 'withAttribute'], $args);
        }

        return call_user_func_array([$this->db(), $method], $args);
    }

    /**
     * Note: 执行方法
     * Date: 2023-05-15
     * Time: 15:41
     * @param string $method 方法名称
     * @param array $args 参数
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        if (isset(static::$macro[static::class][$method])) {
            return call_user_func_array(static::$macro[static::class][$method], $args);
        }

        $model = new static();

        return call_user_func_array([$model->db(), $method], $args);
    }

    /**
     * 延迟保存
     * @access public
     */
    public function __destruct()
    {
        if ($this->lazySave) {
            $this->save();
        }
    }
}