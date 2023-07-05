<?php
declare (strict_types = 1);

namespace Enna\Orm;

use Closure;
use Enna\Orm\Model\Collection;
use http\Params;
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

        if (!empty($this->data)) {
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
    public function exists(bool $exists = true)
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
    public function isExists()
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

    /**
     * Note: 新增数据是否使用replace
     * Date: 2023-06-09
     * Time: 17:20
     * @param bool $replace
     * @return $this
     */
    public function replace(bool $replace = true)
    {
        $this->replace = $replace;

        return $this;
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

    /**
     * Note: 刷新模型数据
     * Date: 2023-06-09
     * Time: 18:22
     * @param bool $relaion 是否刷新关联数据
     * @return $this
     */
    public function refresh(bool $relaion = false)
    {
        if ($this->exists) {
            $this->data = $this->db()->find($this->getPk())->getData();
            $this->origin = $this->data;
            $this->get = [];

            if ($relaion) {
                $this->relation = [];
            }
        }

        return $this;
    }

    /**
     * Note: 保存当前模型对象
     * Date: 2023-06-08
     * Time: 17:39
     * @param array $data 数据
     * @param string|null $sequence 自增序列名
     * @return bool
     */
    public function save(array $data = [], string $sequence = null)
    {
        $this->setAttrs($data);

        if ($this->isEmpty() || $this->trigger('BeforeWrite') === false) {
            return false;
        }

        $result = $this->exists ? $this->updateData() : $this->insertData($sequence);
        if ($result === false) {
            return false;
        }

        $this->trigger('AfterWrite');

        $this->origin = $this->data;
        $this->get = [];
        $this->lazySave = false;

        return true;
    }

    /**
     * Note: 修改写入数据
     * Date: 2023-06-08
     * Time: 17:55
     * @return bool
     */
    public function updateData()
    {
        if ($this->trigger('BeforeUpdate') === false) {
            return false;
        }

        //获取数据
        $data = $this->getChangeData();
        if (empty($data)) {
            if (!empty($this->relationWrite)) {
                $this->autoRelationUpdate();
            }
            return true;
        }

        //自动写入时间戳
        if ($this->autoWriteTimestamp && $this->updateTime) {
            $data[$this->updateTime] = $this->autoWriteTimestamp();
            $this->data[$this->updateTime] = $this->getTimestampValue($data[$this->updateTime]);
        }

        //检查允许的字段
        $allowFields = $this->checkAllowFields();

        //过滤掉子模型绑定父模型的属性
        foreach ($this->relationWrite as $name => $val) {
            if (!is_array($val)) {
                continue;
            }

            foreach ($val as $key) {
                if (isset($data[$key])) {
                    unset($data[$key]);
                }
            }
        }

        //查询对象
        $query = $this->db();
        $query->transaction(function () use ($data, $allowFields, $query) {
            $this->key = null;
            $where = $this->getWhere();

            $result = $query->where($where)
                ->strict(false)
                ->cache(true)
                ->setOption('key', $this->key)
                ->field($allowFields)
                ->update($data);

            if (!empty($this->relationWrite)) {
                $this->autoRelationUpdate();
            }
        });

        //事件
        $this->trigger('AfterUpdate');

        return true;
    }

    /**
     * Note: 插入写入数据
     * Date: 2023-06-08
     * Time: 17:55
     * @param string $sequence 自增名
     * @return bool
     */
    public function insertData(string $sequence = null)
    {
        //事件
        if ($this->trigger('BeforeInsert') === false) {
            return false;
        }

        //获取数据
        $data = $this->data;

        //自动写入时间戳
        if ($this->autoWriteTimestamp) {
            if ($this->createTime && !isset($data[$this->createTime])) {
                $data[$this->createTime] = $this->autoWriteTimestamp();
                $this->data[$this->createTime] = $this->getTimestampValue($data[$this->createTime]);
            }

            if ($this->updateTime && !isset($data[$this->updateTime])) {
                $data[$this->updateTime] = $this->autoWriteTimestamp();
                $this->data[$this->updateTime] = $this->getTimestampValue($data[$this->updateTime]);
            }
        }

        //检查允许字段
        $allowFields = $this->checkAllowFields();

        //查询对象
        $query = $this->db();
        $query->transaction(function () use ($data, $sequence, $allowFields, $query) {
            $result = $query->strict(false)
                ->field($allowFields)
                ->replace($this->replace)
                ->sequence($sequence)
                ->insert($data);

            if ($result) {
                $pk = $this->getPk();

                if (is_string($pk) && (!isset($this->data[$pk]) || $this->data[$pk] == '')) {
                    unset($this->get[$pk]);
                    $this->data[$pk] = $result;
                }
            }

            if (!empty($this->relationWrite)) {
                $this->autoRelationInsert();
            }
        });

        $this->exists = true;
        $this->origin = $this->data;

        $this->trigger('AfterInsert');

        return true;
    }

    /**
     * Note: 检查数据是否允许写入
     * Date: 2023-06-08
     * Time: 18:13
     * @return array
     */
    protected function checkAllowFields()
    {
        if (empty($this->field)) {
            if (!empty($this->schema)) {
                $this->field = array_keys(array_merge($this->schema, $this->jsonType));
            } else {
                $query = $this->db();
                $table = $this->table ? $this->table . $this->suffix : $query->getTable();

                $this->field = $query->getConnection()->getTableFields();
            }

            return $this->field;
        }

        $field = $this->field;

        if ($this->autoWriteTimestamp) {
            array_push($field, $this->createTime, $this->updateTime);
        }

        if (!empty($this->disuse)) {
            $field = array_diff($field, $this->disuse);
        }

        return $field;
    }

    /**
     * Note: 获取当前更新条件
     * Date: 2023-06-08
     * Time: 18:44
     * @return mixed
     */
    public function getWhere()
    {
        $pk = $this->getPk();

        if (is_string($pk) && isset($this->origin[$pk])) {
            $where = [[$pk, '=', $this->origin[$pk]]];
            $this->key = $this->origin[$pk];
        } elseif (is_array($pk)) {
            foreach ($pk as $field) {
                if (isset($this->origin[$field])) {
                    $where[] = [$field, '=', $this->origin[$field]];
                }
            }
        }

        if (empty($this->updateWhere)) {
            $where = empty($this->updateWhere) ? null : $this->updateWhere;
        }

        return $where;
    }

    /**
     * Note: 批量保存数据到模型
     * Date: 2023-06-09
     * Time: 16:35
     * @param iterable $dataSet 数据集合
     * @param bool $replace 是否自动识别更新或写入
     * @return Collection
     */
    public function saveAll(iterable $dataSet, bool $replace = true)
    {
        $query = $this->db();

        $result = $query->transaction(function () use ($replace, $dataSet) {
            $pk = $this->getPk();
            if (is_string($pk) && $replace) {
                $auto = true;
            }

            $result = [];
            $suffix = $this->getSuffix();
            foreach ($dataSet as $key => $data) {
                if ($this->exists || (!empty($auto) || isset($data[$pk]))) {
                    $result[$key] = static::update($data, [], [], $suffix);
                } else {
                    $result[$key] = static::create($data, $this->field, $this->replace, $suffix);
                }
            }

            return $result;
        });

        return $this->toCollection($result);
    }

    /**
     * Note: 写入数据
     * Date: 2023-06-09
     * Time: 17:13
     * @param array $data 数据
     * @param array $allowField 允许写入的字段
     * @param bool $replace 是否自动更新或写入
     * @param string $suffix 数据表后缀
     * @return static
     */
    public static function create(array $data, array $allowField = [], bool $replace = false, string $suffix = '')
    {
        $model = new static();

        if (!empty($allowField)) {
            $model->allowField($allowField);
        }

        if (!empty($suffix)) {
            $model->setSuffix($suffix);
        }

        $model->replace($replace)->save($data);

        return $model;
    }

    /**
     * Note: 删除当前记录
     * Date: 2023-06-09
     * Time: 17:34
     * @return bool
     */
    public function delete()
    {
        if ($this->exists || $this->isEmpty() || $this->trigger('BeforeDelete')) {
            return false;
        }

        $where = $this->getWhere();

        $query = $this->db();
        $query->transaction(function () use ($where, $query) {
            $query->where($where)->delete();

            if (!empty($this->relationWrite)) {
                $this->autoRelationDelete();
            }
        });

        $this->trigger('AfterDelete');

        $this->exists = false;
        $this->lazySave = false;

        return true;
    }

    /**
     * Note: 删除记录
     * Date: 2023-06-09
     * Time: 17:40
     * @param int|string|array $data 主键列表,支持闭包查询条件
     * @param bool $force
     */
    public static function destroy($data, bool $force = false)
    {
        if (empty($data)) {
            return false;
        }

        $model = new static();
        $query = $model->db();

        if (is_array($data) && key($data) !== 0) {
            $query->where($data);
            $data = null;
        } elseif ($data instanceof Closure) {
            $data($query);
            $data = null;
        }

        $resultSet = $query->select($data);

        foreach ($resultSet as $result) {
            $result->force($force)->delte();
        }

        return true;
    }

    /**
     * Note: 更新数据
     * Date: 2023-06-09
     * Time: 17:24
     * @param array $data 数据
     * @param array $where 条件
     * @param array $allowField 允许修改的字段
     * @param string $suffix 数据表后缀
     * @return static
     */
    public static function update(array $data, $where = [], array $allowField = [], string $suffix = '')
    {
        $model = new static();

        if (!empty($allowField)) {
            $model->allowField($allowField);
        }

        if (!empty($suffix)) {
            $model->setSuffix($suffix);
        }

        $model->exists(true)->save($data);

        return $model;
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