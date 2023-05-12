<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Framework\Exception;
use Enna\Framework\Helper\Str;

/**
 * SQL获取类
 * Class Fetch
 * @package Enna\Orm\Db
 */
class Fetch
{
    /**
     * 查询对象
     * @var Query
     */
    protected $query;

    /**
     * Connection对象
     * @var Connection
     */
    protected $connection;

    /**
     * Builder对象
     * @var Builder
     */
    protected $builder;

    public function __construct(Query $query)
    {
        $this->query = $query;
        $this->connection = $query->getConnection();
        $this->builder = $this->connection->getBuilder();
    }

    /**
     * Note: 插入记录
     * Date: 2023-04-24
     * Time: 10:10
     * @param array $data 数据
     * @return string
     */
    public function insert(array $data = [])
    {
        $options = $this->query->parseOptions();

        if (!$this->data) {
            $this->query->setOption('data', $data);
        }

        $sql = $this->builder->insert($this->query);

        return $this->fetch($sql);
    }

    /**
     * Note: 插入记录并获取自增ID
     * Date: 2023-04-24
     * Time: 10:14
     * @param array $data 数据
     * @return string
     */
    public function insertGetId(array $data = [])
    {
        return $this->insert($data);
    }

    /**
     * Note: 保存数据,自动判断是insert还是update
     * Date: 2023-04-24
     * Time: 10:48
     * @param array $data 数据
     * @param bool $forceInsert 是否强制插入
     * @return string
     */
    public function save(array $data = [], bool $forceInsert = false)
    {
        if ($forceInsert) {
            $this->insert($data);
        }

        $data = array_merge($this->query->getOptions('data') ?: [], $data);

        $this->query->setOption('data', $data);

        if ($this->query->getOptions('where')) {
            $isUpdate = true;
        } else {
            $isUpdate = $this->query->parseUpdateData($data);
        }

        return $isUpdate ? $this->update() : $this->insert();
    }

    /**
     * Note: 批量插入记录
     * Date: 2023-04-24
     * Time: 11:44
     * @param array $dataSet 数据集
     * @param int|null $limit 每次写入数据限制
     * @return string
     */
    public function insertAll(array $dataSet = [], int $limit = null)
    {
        $options = $this->query->parseOptions();

        if (empty($dataSet)) {
            $dataSet = $options['data'];
        }

        if (empty($limit) && !empty($options['limit'])) {
            $limit = $options['limit'];
        }

        if ($limit) {
            $array = array_column($dataSet, $limit, true);
            $fetchSql = [];
            foreach ($array as $item) {
                $sql = $this->builder->insertAll($this->query, $item);
                $bind = $this->query->getBind();

                $fetchSql[] = $this->connection->getRealSql($sql, $bind);
            }

            return implode(',', $fetchSql);
        }

        $sql = $this->builder->insertAll($this->query, $dataSet);

        return $sql;
    }

    /**
     * Note: 执行insert select语句
     * Date: 2023-04-24
     * Time: 11:56
     * @param array $fields 要插入的数据表字段
     * @param string $table 要查询的数据表
     * @return string
     */
    public function selectInsert(array $fields, string $table)
    {
        $this->query->parseOptions();

        $sql = $this->builder->selectInsert($this->query, $fields, $table);

        return $this->fetch($sql);
    }

    /**
     * Note: 删除记录
     * Date: 2023-04-24
     * Time: 14:24
     * @param array $data 表达式
     * @return string
     */
    public function delete(array $data = [])
    {
        $options = $this->query->parseOptions();

        if (!is_null($data) && $data != true) {
            $this->query->parsePkWhere($data);
        }

        if (!empty($options['soft_delete'])) {
            [$field, $condition] = $options['soft_delete'];
            if ($condition) {
                $this->query->setOption('soft_delete', null);
                $this->query->setOption('data', [$field => $condition]);

                $sql = $this->builder->delete($this->query);
                return $this->fetch($sql);
            }
        }

        $sql = $this->builder->delete($this->query);
        return $this->fetch($sql);
    }

    /**
     * Note: 更新记录
     * Date: 2023-04-24
     * Time: 14:37
     * @param array $data 数据
     * @return string
     */
    public function update(array $data = [])
    {
        $options = $this->query->parseOptions();

        if (!empty($data)) {
            $data = array_merge($this->options['data'] ?? [], $data);
        }

        $pk = $this->query->getPk();

        if (empty($options['where'])) {
            if (is_string($pk) && isset($data[$pk])) {
                $this->query->where($pk, '=', $data[$pk]);
            } elseif (is_array($pk)) {
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $this->query->where($field, '=', $data[$field]);
                    } else {
                        throw new Exception('miss complex primary data');
                    }
                    unset($data[$field]);
                }
            }

            if (empty($this->query->getOptions('where'))) {
                throw new Exception('miss update condition');
            }
        }

        $this->query->setOption('data', $data);

        $sql = $this->builder->update($this->query);

        return $this->fetch($sql);
    }

    /**
     * Note: count查询
     * Date: 2023-04-24
     * Time: 15:04
     * @param string $field 字段
     * @return string
     */
    public function count(string $field = '*')
    {
        $options = $this->query->parseOptions();

        if (!empty($options['group'])) {
            $bind = $this->query->getBind();
            $subSql = $this->query->field('count(' . $field . ') as count')
                ->options($options)
                ->bind($bind)
                ->buildSql();

            $query = $this->query->newQuery()->table([$subSql => '_group_count_']);
            return $query->fetchSql()->aggregate('COUNT', '*');
        } else {
            return $this->aggregate('COUNT', '*');
        }
    }

    /**
     * Note: SUM查询
     * Date: 2023-04-24
     * Time: 15:11
     * @param string $field 字段
     * @return string
     */
    public function sum(string $field)
    {
        return $this->aggregate('SUM', $field);
    }

    /**
     * Note: AVG查询
     * Date: 2023-04-24
     * Time: 15:12
     * @param string $field 字段
     * @return string
     */
    public function avg(string $field)
    {
        return $this->aggregate('AVG', $field);
    }

    /**
     * Note: MIN查询
     * Date: 2023-04-24
     * Time: 15:13
     * @param string $field 字段
     * @return string
     */
    public function min(string $field)
    {
        return $this->aggregate('MIN', $field);
    }

    /**
     * Note: MAX查询
     * Date: 2023-04-24
     * Time: 15:13
     * @param string $field 字段
     * @return string
     */
    public function max(string $field)
    {
        return $this->aggregate('MAX', $field);
    }

    /**
     * Note: 聚合查询
     * Date: 2023-04-22
     * Time: 17:21
     * @param string $aggregate 聚合方法
     * @param string $field 字段名
     * @return string
     */
    protected function aggregate(string $aggregate, string $field)
    {
        $this->query->parseOptions();

        $field = $aggregate . '(' . $this->builder->parseKey($query, $field, true) . ') as ' . strtolower($aggregate);

        return $this->value($field, 0, false);
    }

    /**
     * Note: 得到某个字段的值
     * Date: 2023-04-22
     * Time: 17:22
     * @param string $field 字段名
     * @param mixed $default 默认值
     * @param bool $one 是否查询一个
     * @return string
     */
    public function value(string $field, $default = null, bool $one = true)
    {
        $options = $this->query->parseOptions();

        if (isset($options['field'])) {
            $this->query->removeOption('fiele');
        }

        $this->query->setOption('field', (array)$field);

        $sql = $this->builder->select($this->query, $one);

        if (isset($options['field'])) {
            $this->query->setOption('field', $options['field']);
        } else {
            $this->query->removeOption('field');
        }

        return $this->fetch($sql);
    }

    /**
     * Note: 得到某列字段的值
     * Date: 2023-04-24
     * Time: 10:08
     * @param string $field 字段名
     * @param string $key 索引字段
     * @return string
     */
    public function column(string $field, string $key = '')
    {
        $options = $this->query->getOptions();

        if (isset($options['fild'])) {
            $this->query->removeOption('field');
        }

        if ($key && $field != '*') {
            $field = $key . ',' . $field;
        }

        $field = array_map('trim', explode(',', $field));

        $this->query->setOption('field', $field);

        $sql = $this->builder->select($this->query);

        if (isset($options['field'])) {
            $this->query->setOption('field', $options['field']);
        } else {
            $this->query->removeOption('field');
        }

        return $this->fetch($sql);
    }

    /**
     * Note: 查找单条记录,返回SQL语句
     * Date: 2023-04-24
     * Time: 15:22
     * @param mixed $data 数据
     * @return string
     */
    public function find($data = null)
    {
        $this->query->parseOptions();

        if (!is_null($data)) {
            $this->query->parsePkWhere($data);
        }

        $sql = $this->builder->select($this->query, true);

        return $this->fetch($sql);
    }

    /**
     * Note: 查找单条记录,不存在则抛出异常
     * Date: 2023-04-24
     * Time: 15:17
     * @param null $data
     */
    public function findOrFail()
    {
        return $this->find($data);
    }

    /**
     * Note: 查找单条记录,不存在则返回空数组
     * Date: 2023-04-24
     * Time: 15:19
     * @param null $data
     */
    public function findOrEmpty($data = null)
    {
        return $this->find($data);
    }

    /**
     * Note: 查找记录:返回SQL
     * Date: 2023-03-31
     * Time: 11:14
     * @param mixed $data
     * @return string
     */
    public function select($data = null)
    {
        $this->query->parseOptions();

        if (!is_null($data)) {
            $this->query->parsePkWhere($data);
        }

        $sql = $this->builder->select($this->query);

        return $this->fetch($sql);
    }

    /**
     * Note: 查找多条记录,不存在抛出异常
     * Date: 2023-04-24
     * Time: 15:17
     * @param null $data
     * @return string
     */
    public function selectOrFail($data = null)
    {
        return $this->selectInsert($data);
    }

    /**
     * Note: 获取实际的SQL语句
     * Date: 2023-03-31
     * Time: 11:18
     * @param string $sql
     * @return string
     */
    public function fetch(string $sql)
    {
        $bind = $this->query->getBind();

        return $this->connection->getRealSql($sql, $bind);
    }

    public function __call($method, $args)
    {
        if (strtolower(substr($method, 0, 5)) == 'getby') { //根据某个字段获取信息
            $field = Str::snake(substr($method, 5));
            return $this->where($field, '=', $args[0])->find();
        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') { //根据某个字段获取某个值
            $field = Str::snake(substr($method, 10));
            return $this->where($field, '=', $args[0])->value($args[1]);
        }

        $result = call_user_func_array([$this->query, $method], $args);
        return $result == $this->query ? $this : $result;
    }
}