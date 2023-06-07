<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Framework\Cache\TagSet;
use Enna\Framework\Exception;
use Enna\Framework\Helper\Collection;
use Enna\Orm\Contract\ConnectionInterface;
use Enna\Orm\Db\Exception\DbException;
use Enna\Framework\Helper\Str;
use Enna\Orm\Model;
use Enna\Orm\Paginator;
use Predis\Command\Redis\MIGRATE;

abstract class BaseQuery
{
    use Concern\ModelRelationQuery;
    use Concern\Transaction;
    use Concern\WhereQuery;
    use Concern\AggregateQuery;
    use Concern\ResultOperation;
    use Concern\TimeFieldQuery;

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
        $query = new static($this->connection);

        if ($this->model) {
            $this->model($this->model);
        }

        if (isset($this->options['table'])) {
            $query->table($this->options['table']);
        } else {
            $query->name($this->name);
        }

        if (isset($this->options['json'])) {
            $query->json($this->options['json'], $this->options['json_assoc']);
        }

        if (isset($this->options['field_type'])) {
            $query->setFieldType($this->options['field_type']);
        }

        return $query;
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

    /**
     * Note: 指定查询字段
     * Date: 2023-04-10
     * Time: 17:09
     * @param mixed $field 字段信息
     * @return $this
     */
    public function field($field)
    {
        if (empty($field)) {
            return $this;
        } elseif ($field instanceof Raw) {
            $this->options['field'][] = $field;

            return $this;
        }

        if (is_string($field)) {
            if (preg_match('[\'\"\<\(]', $field)) {
                return $this->fieldRaw($field);
            }

            $field = array_map('trim', explode(',', $field));
        }

        if ($field === true) {
            $fields = $this->getTableFields();
            $field = $fields ?: ['*'];
        }

        if (isset($this->options['field'])) {
            $field = array_merge((array)$this->options['field'], $field);
        }

        $this->options['field'] = array_unique($field);

        return $this;
    }

    /**
     * Note: 指定要排除的查询字段
     * Date: 2023-04-10
     * Time: 17:20
     * @param array|string $field 要排除的字段
     * @return $this
     */
    public function withoutField($field)
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        $fields = $this->getTableFields();
        $field = $fields ? array_diff($fields, $fields) : $field;

        if (isset($this->options['field'])) {
            $field = array_merge((array)$this->options['field'], $field);
        }

        $this->options['field'] = array_unique($field);

        return $this;
    }

    /**
     * Note: 指定其他数据表的查询字段
     * Date: 2023-04-11
     * Time: 9:41
     * @param mixed $field 字段信息
     * @param string $tableName 数据表名
     * @param string $prefix 字段前缀
     * @param string $alias 别名前缀
     * @return $this
     */
    public function tableField($field, string $tableName, string $prefix = '', string $alias = '')
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        if ($field === true) {
            $fields = $this->getTableFields();
            $field = $fields ?: ['*'];
        }

        $prefix = $prefix ?: $tableName;
        foreach ($field as $key => &$val) {
            if (is_numeric($key) && $alias) {
                $field[$prefix . '.' . $val] = $alias . $val;
                unset($field[$key]);
            } elseif (is_numeric($key)) {
                $val = $prefix . '.' . $val;
            }
        }

        if (isset($this->options['field'])) {
            $field = array_merge((array)$this->options['field'], $field);
        }

        $this->options['field'] = array_unique($field);

        return $this;
    }

    /**
     * Note: 查询参数批量复制
     * Date: 2023-04-10
     * Time: 14:17
     * @param array $options 表达式参数
     * @return $this
     */
    protected function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Note: 设置当前查询参数
     * Date: 2023-04-03
     * Time: 16:19
     * @param string $option 参数名
     * @param mixed $value 参数值
     * @return $this
     */
    public function setOption(string $option, $value)
    {
        $this->options[$option] = $value;

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
     * Note: 获取数据库的配置参数
     * Date: 2023-04-10
     * Time: 17:56
     * @param string $name 参数名称
     * @return mixed
     */
    public function getConfig(string $name = '')
    {
        return $this->connection->getConfig($name);
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

        return $this->prefix . Str::snake($name);
    }

    /**
     * Note: 去除查询参数
     * Date: 2023-04-03
     * Time: 16:17
     * @param string $option 参数名
     * @return $this
     */
    public function removeOption(string $option = '')
    {
        if ($option === '') {
            $this->options = [];
            $this->bind = [];
        } elseif (isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }

    /**
     * Note: 设置当前字段添加的表别名
     * Date: 2023-05-23
     * Time: 11:08
     * @param string $via 表别名
     * @return $this
     */
    public function via(string $via = '')
    {
        $this->options['via'] = $via;

        return $this;
    }

    /**
     * Note: 指定数据库主键
     * Date: 2023-04-18
     * Time: 18:38
     * @param string $pk 主键
     * @return $this;
     */
    public function pk($pk)
    {
        $this->pk = $pk;

        return $this;
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

    /**
     * Note: 设置主数据库读取信息
     * Date: 2023-04-18
     * Time: 18:36
     * @param bool $strict 是否主数据库
     * @return $this
     */
    public function strict(bool $strict = true)
    {
        $this->options['strict'] = $strict;

        return $this;
    }

    /**
     * Note: 指定查询lock
     * Date: 2023-04-18
     * Time: 18:32
     * @param bool|string $lock 是否lock
     * @return $this
     */
    public function lock($lock = false)
    {
        $this->options['lock'] = $lock;

        if ($lock) {
            $this->options['master'] = true;
        }

        return $this;
    }

    /**
     * Note: 指定查询数量
     * Date: 2023-04-11
     * Time: 9:53
     * @param int $offset 起始位置
     * @param int $length 查询数量
     * @return $this
     */
    public function limit(int $offset, int $length)
    {
        $this->options['limit'] = $offset . ($length ? ',' . $length : '');

        return $this;
    }

    /**
     * Note: 指定分页
     * Date: 2023-04-11
     * Time: 9:57
     * @param int $page 页数
     * @param int|null $listRows 每页数量
     * @return $this
     */
    public function page(int $page, int $listRows = null)
    {
        $this->options['page'] = [$page, $listRows];

        return $this;
    }

    /**
     * Note: 指定排序
     * Date: 2023-04-11
     * Time: 10:14
     * @param string|array|Raw $field 排序字段
     * @param string $order 排序
     * @return $this
     */
    public function order($field, string $order = '')
    {
        if (empty($field)) {
            return $this;
        } elseif ($field instanceof Raw) {
            $this->options['order'][] = $field;
            return $this;
        }

        if (is_string($field)) {
            if (strpos($field, ',')) {
                $field = array_map('trim', explode(',', $field));
            } else {
                $field = empty($order) ? $field : [$field => $order];
            }
        }

        if (!isset($this->options['order'])) {
            $this->options['order'] = [];
        }

        if (is_array($field)) {
            $this->options['order'] = array_merge($this->options['order'], $field);
        } else {
            $this->options['order'][] = $field;
        }

        return $this;
    }

    /**
     * Note: 分页查询
     * Date: 2023-04-11
     * Time: 11:02
     * @param int|array $listRows 每页数量|数组表示配置参数
     * @param bool|int $simple 简洁分页|总数
     * @return Paginator
     * @throws Exception
     */
    public function paginate($listRows = null, $simple = false)
    {
        if (is_string($simple)) {
            $total = $simple;
            $simple = false;
        }

        $defaultConfig = [
            'var_page' => 'page',
            'list_rows' => 15,
            'query' => [],
            'fragment' => '',
        ];

        if (is_array($listRows)) {
            $config = array_merge($defaultConfig, $listRows);
            $listRows = intval($config['list_rows']);
        } else {
            $config = $defaultConfig;
            $listRows = intval($listRows ?: $config['list_rows']);
        }

        $config['path'] = $config['path'] ?? Paginator::getCurrentPath();

        $page = isset($config['page']) ? (int)$config['page'] : Paginator::getCurrentPage($config['var_page']);
        $page = $page < 1 ? 1 : $page;

        $config['path'] = $config['path'] ?? Paginator::getCurrentPath();

        if (!isset($total) && !$simple) {
            $options = $this->getOptions();

            $bind = $this->bind;
            $total = $this->count();
            $results = $total > 0 ? $this->options($options)->bind($bind)->page($page, $listRows)->select() : [];
        } elseif ($simple) {
            $results = $this->limit(($page - 1) * $listRows, $listRows + 1)->select();
            $total = null;
        } else {
            $results = $this->page($page, $listRows)->select();
        }

        $this->removeOption('limit');
        $this->removeOption('page');

        return Paginator::make($results, $listRows, $page, $total, $simple, $config);
    }

    /**
     * Note: 根据数字类型字段进行分页查询(大数据)
     * Date: 2023-04-18
     * Time: 14:29
     * @param int|array $listRows 每页数量或者分页配置
     * @param string|null $key 分页索引键
     * @param string|null $sort 索引键排序
     * @return Paginator
     * @throws Exception
     */
    public function paginateX($listRows = null, string $key = null, string $sort = null)
    {
        $defaultConfig = [
            'var_page' => 'page',
            'list_rows' => 15,
            'query' => [],
            'fragment' => '',
        ];

        $config = is_array($listRows) ? array_merge($defaultConfig, $listRows) : $defaultConfig;
        $listRows = is_int($listRows) ? $listRows : $config['list_rows'];
        $page = isset($config['page']) ? (int)$config['page'] : Paginator::getCurrentPage($config['var_page']);
        $page = $page < 1 ? 1 : $page;

        $config['path'] = $config['path'] ?? Paginator::getCurrentPath();

        $key = $key ?: $this->getPk();
        $options = $this->getOptions();

        if (is_null($sort)) {
            $order = $options['order'] ?? '';
            if (!empty($order)) {
                $sort = $order[$key] ?? 'desc';
            } else {
                $sort = 'desc';
                $this->order($key, 'desc');
            }
        } else {
            $this->order($key, $sort);
        }

        $newOption = $options;
        unset($newOption['field'], $newOption['page']);

        $data = $this->newQuery()
            ->options($newOption)
            ->field($key)
            ->order($key, $sort)
            ->limit(1)
            ->find();

        $result = $data[$key] ?? 0;
        if (is_numeric($result)) {
            $lastId = $sort == 'asc' ? ($result - 1) + ($page - 1) * $listRows : ($result + 1) + ($page - 1) * $listRows;
        } else {
            throw new Exception('not support type');
        }

        $results = $this->when($lastId, function ($query) use ($key, $sort, $lastId) {
            $query->where($key, $sort == 'asc' ? '>' : '<', $lastId);
        })->limit($listRows)->select();

        $this->options($options);

        return Paginator::make($results, $listRows, $page, null, true, $config);
    }

    /**
     * Note: 根据最后ID查询N条数据
     * Date: 2023-04-18
     * Time: 14:56
     * @param int $limit 条数
     * @param int|null $lastId 最后ID
     * @param string|null $key 索引键,默认主键
     * @param string|null $sort 索引键排序
     * @return array
     * @throws Exception
     */
    public function more(int $limit, $lastId = null, string $key = null, string $sort = null)
    {
        $key = $key ?: $this->getPk();

        if (is_null($sort)) {
            $order = $this->getOptions('order');
            if (!empty($order)) {
                $sort = $order[$key] ?? 'desc';
            } else {
                $sort = 'desc';
                $this->order($key, 'desc');
            }
        } else {
            $this->order($key, $sort);
        }

        $result = $this->when($lastId, function ($query) use ($key, $sort, $lastId) {
            $query->where($key, $sort == 'asc' ? '>' : '<', $lastId);
        })->limit($limit)->select();

        $last = $result->last();

        return [
            'data' => $result,
            'lastId' => $last ? $last[$key] : null
        ];
    }

    /**
     * Note: 设置JSON字段信息
     * Date: 2023-04-10
     * Time: 15:53
     * @param array $json JSON字段
     * @param bool $assoc 是否取出数组
     * @return $this
     */
    public function json(array $json = [], bool $assoc = false)
    {
        $this->options['json'] = $json;
        $this->options['json_assoc'] = $assoc;

        return $this;
    }

    /**
     * Note: 设置字段类型
     * Date: 2023-04-10
     * Time: 15:55
     * @param array $type 字段类型信息
     * @return $this
     */
    public function setFieldType(array $type)
    {
        $this->options['field_type'] = $type;

        return $this;
    }

    /**
     * Note: 插入记录
     * Date: 2023-04-19
     * Time: 15:30
     * @param array $data 数据
     * @param bool $getLastInsID 返回自增主键
     * @return int|string
     */
    public function insert(array $data = [], bool $getLastInsID = false)
    {
        if (!empty($data)) {
            $this->options['data'] = $data;
        }

        return $this->connection->insert($this, $getLastInsID);
    }

    /**
     * Note: 插入数据并获取自增ID
     * Date: 2023-04-19
     * Time: 15:32
     * @param array $data 数据
     * @return int|string
     */
    public function insertGetId(array $data = [])
    {
        return $this->insert($data, true);
    }

    /**
     * Note: 批量插入记录
     * Date: 2023-04-19
     * Time: 15:39
     * @param array $dataSet 数据集
     * @param int $limit 每次写入记录限制
     * @return int
     */
    public function insertAll(array $dataSet = [], int $limit = 0)
    {
        if (empty($dataSet)) {
            $dataSet = $this->options['data'] ?? [];
        }

        if (empty($limit) && !empty($this->options['limit']) && is_numeric($this->options['limit'])) {
            $limit = (int)$this->options['limit'];
        }

        return $this->connection->insertAll($this, $dataSet, $limit);
    }

    /**
     * Note: 通过select方式插入记录
     * Date: 2023-04-03
     * Time: 11:58
     * @param array $fields 要插入的数据表字段名
     * @param string $table 要插入的数据表名
     * @return int
     */
    public function selectInsert(array $fields, string $table)
    {
        return $this->connection->selectInsert($this, $fields, $table);
    }

    /**
     * Note: 保存记录,自动判断insert或update
     * Date: 2023-04-19
     * Time: 18:06
     * @param array $data 数据
     * @param bool $forceInsert 是否Insert
     * @return int
     */
    public function save(array $data = [], bool $forceInsert = false)
    {
        if ($forceInsert) {
            $this->insert($data);
        }

        $this->options['data'] = array_merge($this->options['data'] ?? [], $data);

        if (!empty($this->options['where'])) {
            $isUpdate = true;
        } else {
            $isUpdate = $this->parseUpdateData($this->options['data']);
        }

        return $isUpdate ? $this->update() : $this->insert();
    }

    /**
     * Note: 删除记录
     * Date: 2023-04-19
     * Time: 16:18
     * @param array|bool $data 数据
     * @return int
     * @throws DbException
     */
    public function delete($data = null)
    {
        if ($data !== true && !is_null($data)) {
            $this->parsePkWhere($data);
        }

        if ($data !== true && empty($this->options['where'])) {
            throw new DbException('no delete condition');
        }

        if (!empty($this->options['soft_delete'])) {
            list($field, $condition) = $this->options['soft_delete'];
            if ($condition) {
                unset($this->options['soft_delete']);

                $this->options['data'] = [$field => $condition];
                return $this->connection->update($this);
            }
        }

        $this->options['data'] = $data;

        return $this->connection->delete($this);
    }

    /**
     * Note: 更新记录
     * Date: 2023-04-19
     * Time: 15:47
     * @param array $data 数据
     * @return int
     * @return Exception
     */
    public function update(array $data)
    {
        if (!empty($data)) {
            $this->options['data'] = array_merge($this->options['data'] ?? [], $data);
        }

        if (empty($this->options['where'])) {
            $this->parseUpdateData($this->options['data']);
        }

        if (empty($this->options['where'])) {
            throw new Exception('miss update condition');
        }

        return $this->connection->update($this);
    }

    /**
     * Note: 查询SQL UNION
     * Date: 2023-04-10
     * Time: 18:55
     * @param mixed $union UNION
     * @param bool $all 是否UNION ALL
     * @return $this
     */
    public function union($union, bool $all = false)
    {
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';

        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }

        return $this;
    }

    /**
     * Note: 查询SQL UNION ALL
     * Date: 2023-04-10
     * Time: 18:58
     * @param mixed $union UNION
     * @return $this
     */
    public function unionAll($union)
    {
        return $this->union($union, true);
    }

    /**
     * Note: 得到某个字段的值
     * Date: 2023-04-10
     * Time: 18:30
     * @param string $field 字段名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function value(string $field, $default = null)
    {
        $this->connection->value($field, $default);
    }

    /**
     * Note: 得到某个列的数组
     * Date: 2023-04-10
     * Time: 18:32
     * @param string|array $field 字段名
     * @param string $key 索引
     * @return array
     */
    public function column($field, string $key = '')
    {
        $this->connection->column($field, $key);
    }

    /**
     * Note: 查询记录
     * Date: 2023-04-20
     * Time: 11:48
     * @param mixed $data 数据
     * @return Collection
     * @throws DbException
     * @throws Exception\DataNotFoundException
     * @throws Exception\ModelNotFoundException
     */
    public function select($data = null)
    {
        if (!is_null($data)) {
            $this->parsePkWhere($data);
        }

        $resultSet = $this->connection->select($this);

        if (!empty($this->options['fail']) && count($resultSet) == 0) {
            $this->throwNotFound();
        }

        if (!empty($this->model)) {
            $resultSet = $this->resultSetToModelCollection($resultSet);
        } else {
            $this->resultSet($resultSet);
        }

        return $resultSet;
    }

    /**
     * Note: 查询单条记录
     * Date: 2023-04-20
     * Time: 11:50
     * @param mixed $data 数据
     * @return arary|Model|static
     */
    public function find($data = null)
    {
        if (!is_null($data)) {
            $this->parsePkWhere($data);
        }

        if (empty($this->options['where']) && empty($this->options['order'])) {
            $result = [];
        } else {
            $result = $this->connection->find($this);
        }

        if (empty($result)) {
            return $this->resultToEmpty();
        }

        if (!empty($this->model)) {
            $this->resultToModel($result, $this->options);
        } else {
            $this->result($result);
        }

        return $result;
    }

    /**
     * Note: 获取最近一次查询的SQL语句
     * Date: 2023-04-10
     * Time: 18:01
     * @return string
     */
    public function getLastSql()
    {
        return $this->connection->getLastSql();
    }

    /**
     * Note: 获取返回或影响的记录数
     * Date: 2023-04-10
     * Time: 18:03
     * @return int
     */
    public function getNumRows()
    {
        return $this->connection->getNumRows();
    }

    /**
     * Note: 获取插入的ID
     * Date: 2023-04-10
     * Time: 18:06
     * @param string $sequence 自增序列名
     * @return mixed
     */
    public function getLastInsID()
    {
        return $this->connection->getLastInsID();
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
     * Note: 分析数据是否存在更新条件
     * Date: 2023-04-19
     * Time: 15:58
     * @param array $data 数据
     * @return bool
     * @throws Exception
     */
    public function parseUpdateData(&$data)
    {
        $pk = $this->getPk();
        $isUpdate = false;
        if (is_string($pk) && isset($data[$pk])) {
            $this->where($pk, '=', $data[$pk]);
            usnet($data[$pk]);
            $isUpdate = true;
        } elseif (is_array($pk)) {
            foreach ($pk as $field) {
                if (isset($data[$field])) {
                    $this->where($field, '=', $data[$field]);
                    $isUpdate = true;
                } else {
                    throw new Exception('miss complex primary data');
                }
                unset($data[$field]);
            }
        }

        return $isUpdate;
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
        $pk = $this->getPk();
        if (is_string($pk)) {
            if (is_array($data)) {
                $this->where($key, 'in', $data);
            } else {
                $this->where($key, '=', $data);
            }
        } elseif (is_array($pk)) {
            foreach ($pk as $field) {
                if (isset($data[$field])) {
                    $this->where($field, '=', $data[$field]);
                } else {
                    throw new Exception('miss complex primary data');
                }
            }
        }
    }

    /**
     * Note: 获取模型的更新条件
     * Date: 2023-05-26
     * Time: 16:44
     * @param array $options 查询参数
     * @return mixed
     */
    protected function getModelUpdateCondition(array $options)
    {
        return $options['where']['AND'] ?? null;
    }

    public function __call(string $method, array $args)
    {
        if (strtolower(substr($method, 0, 5)) == 'getby') { //根据某个字段获取信息
            $field = Str::snake(substr($method, 5));
            return $this->where($field, '=', $args[0])->find();
        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') { //根据某个字段获取某个值
            $field = Str::snake(substr($method, 10));
            return $this->where($field, '=', $args[0])->value($args[1]);
        } elseif (strtolower(substr($method, 0, 7)) == 'whereor') {
            $field = Str::snake(substr($method, 7));
            array_unshift($args, $field);

            return call_user_func_array([$this, 'whereOr'], $args);
        } elseif (strtolower(substr($method, 9, 5)) == 'where') {
            $field = Str::snake(substr($method, 5));
            array_unshift($args, $field);

            return call_user_func_array([$this, 'where'], $args);
        } elseif ($this->model && method_exists($method, 'scope' . $method)) {
            $method = 'scope' . $method;
            array_unshift($args, $this);

            call_user_func_array([$this->model, $method], $args);
            return $this;
        } else {
            throw new DbException('method not exist:' . static::class . '->' . $method);
        }
    }
}