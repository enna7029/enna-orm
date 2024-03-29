<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Framework\Exception;

class Query extends BaseQuery
{
    /**
     * Note: 存储过程调用
     * Date: 2023-03-29
     * Time: 17:36
     * @param bool $procedure 是否为存储过程调用
     * @return $this
     */
    public function procedure(bool $procedure = true)
    {
        $this->options['procedure'] = $procedure;

        return $this;
    }

    /**
     * Note: 获取执行的SQL语句而不进行实际查询
     * Date: 2023-03-30
     * Time: 9:34
     * @param bool $fetch 是否返回SQL
     * @return $this|Fetch
     */
    public function fetchSql(bool $fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;

        if ($fetch) {
            return new Fetch($this);
        }

        return $this;
    }

    /**
     * Note: 表达式方式表示当前操作的数据表
     * Date: 2023-03-30
     * Time: 15:20
     * @param string $table 表名
     * @return $this
     */
    public function tableRaw(string $table)
    {
        $this->options['table'] = new Raw($table);

        return $this;
    }

    /**
     * Note: 表达式方式指定查询字段
     * Date: 2023-03-30
     * Time: 17:40
     * @param string $field 字段名
     * @return $this
     */
    public function fieldRaw(string $field)
    {
        $this->options['field'] = new Raw($field);

        return $this;
    }

    /**
     * Note: 表达式方式指定field排序
     * Date: 2023-03-30
     * Time: 17:43
     * @param string $field 字段名
     * @param array $bind 参数绑定
     * @return $this
     */
    public function orderRaw(string $field, array $bind = [])
    {
        $this->options['order'][] = new Raw($field, $bind);

        return $this;
    }

    /**
     * Note: 指定field排序 orderField('id',[1,2,3],'desc')
     * Date: 2023-03-30
     * Time: 17:47
     * @param string $filed 字段名
     * @param array $values 排序值
     * @param string $order 排序方式
     * @return $this
     */
    public function orderField(string $filed, array $values, string $order = '')
    {
        if (!empty($values)) {
            $values['sort'] = $order;

            $this->options['order'][$filed] = $values;
        }

        return $this;
    }

    /**
     * Note: 随机排序
     * Date: 2023-03-30
     * Time: 17:49
     * @return $this
     */
    public function orderRand()
    {
        $this->options['order'][] = '[rand]';

        return $this;
    }

    /**
     * Note: 使用表达式设置数据
     * Date: 2023-03-30
     * Time: 18:10
     * @param string $field 字段名
     * @param string $value 字段值
     * @return $this
     */
    public function exp(string $field, string $value)
    {
        $this->options['data'][$field] = new Raw($value);

        return $this;
    }

    /**
     * Note: 批处理指定SQL语句
     * Date: 2023-03-30
     * Time: 18:12
     * @param array $sql SQL批处理指令
     * @return bool
     */
    public function batchQuery(array $sql = [])
    {
        return $this->connection->batchQuery($this, $sql);
    }

    /**
     * Note: USING支持,用于多表删除
     * Date: 2023-03-30
     * Time: 18:21
     * @param mixed $using USING
     * @return $this
     */
    public function using($using)
    {
        $this->options['using'] = $using;

        return $this;
    }

    /**
     * Note: 指定group查询
     * Date: 2023-03-30
     * Time: 18:22
     * @param string|array $group GROUP
     * @return $this
     */
    public function group($group)
    {
        $this->options['group'] = $group;

        return $this;
    }

    /**
     * Note: 指定having查询
     * Date: 2023-03-30
     * Time: 18:23
     * @param string $having having
     * @return $this
     */
    public function having(string $having)
    {
        $this->options['having'] = $having;

        return $this;
    }

    /**
     * Note: 指定distinct查询
     * Date: 2023-03-30
     * Time: 18:24
     * @param bool $distinct 是否唯一
     * @return $this
     */
    public function distinct(bool $distinct = true)
    {
        $this->options['distinct'] = $distinct;

        return $this;
    }

    /**
     * Note: 指定强制使用索引
     * Date: 2023-03-30
     * Time: 18:36
     * @param string $force 索引名称
     * @return $this
     */
    public function force(string $force)
    {
        $this->options['force'] = $force;

        return $this;
    }

    /**
     * Note: 查询注释
     * Date: 2023-03-30
     * Time: 18:40
     * @param string $comment 注释
     * @return $this
     */
    public function comment(string $comment)
    {
        $this->options['comment'] = $comment;

        return $this;
    }

    /**
     * Note: 设置是否replace
     * Date: 2023-03-30
     * Time: 18:42
     * @param bool $replace 是否使用replace写入数据
     * @return $this
     */
    public function replace(bool $replace = true)
    {
        $this->options['replace'] = $replace;

        return $this;
    }

    /**
     * Note: 设置当前查询所在的分区
     * Date: 2023-03-30
     * Time: 18:49
     * @param string|array $partition 分区名称
     * @return $this
     */
    public function partition($partition)
    {
        $this->options['partition'] = $partition;

        return $this;
    }

    /**
     * Note: 设置duplicate
     * Date: 2023-03-30
     * Time: 18:50
     * @param array|string|Raw $duplicate
     * @return $this
     */
    public function duplicate($duplicate)
    {
        $this->options['duplicate'] = $duplicate;

        return $this;
    }

    /**
     * Note: 设置查询的额外参数
     * Date: 2023-03-30
     * Time: 18:52
     * @param string $extra 额外信息
     * @return $this
     */
    public function extra(string $extra)
    {
        $this->options['extra'] = $extra;

        return $this;
    }

    /**
     * Note: 创建子查询SQL
     * Date: 2023-03-30
     * Time: 18:53
     * @param bool $sub 是否添加括号
     * @return string
     * @throws Exception
     */
    public function buildSql(bool $sub = true)
    {
        return $sub ? '(' . $this->fetchSql()->select() . ')' : $this->fetchSql()->select();
    }

    /**
     * Note: 获取当前数据库的主键
     * Date: 2023-03-31
     * Time: 15:33
     * @return array|string
     */
    public function getPk()
    {
        if (empty($this->pk)) {
            $this->pk = $this->connection->getPk($this->getTable());
        }

        return $this->pk;
    }

    /**
     * Note: 获取当前数据库的自增主键
     * Date: 2023-03-31
     * Time: 15:34
     * @return string
     */
    public function getAutoInc()
    {
        if (empty($this->autoinc)) {
            $this->autoinc = $this->connection->getAutoinc($this->getTable());
        }

        return $this->autoinc;
    }

    /**
     * Note: 字段值增长
     * Date: 2023-04-22
     * Time: 11:27
     * @param string $field 字段
     * @param float|int $step 增长值
     * @return $this
     */
    public function inc(string $field, float $step = 1)
    {
        $this->options['data'][$field] = ['INC', $step];

        return $this;
    }

    /**
     * Note: 字段值减少
     * Date: 2023-04-22
     * Time: 11:32
     * @param string $field 字段值
     * @param float|int $step 减少值
     * @return $this
     */
    public function dec(string $field, float $step = 1)
    {
        return $this->options['data'][$field] = ['DEC', $step];

        return $this;
    }

    /**
     * Note: 获取当前的查询标识
     * Date: 2023-04-22
     * Time: 11:45
     * @param mixed $data 要序列化的数据
     * @return string
     */
    public function getQueryid($data = null)
    {
        return md5($this->getConfig('database') . serialize(var_export($data ?: $this->options, true))) . serialize($this->getBind(false));
    }

    /**
     * Note: 执行查询但只返回PDOStatement对象
     * Date: 2023-04-22
     * Time: 11:49
     * @return \PDOStatement
     */
    public function getPdo()
    {
        return $this->connection->pdo($this);
    }

    /**
     * Note: 使用游标查询记录
     * Date: 2023-04-22
     * Time: 14:13
     * @param mixed $data 数据
     * @return \Generator
     */
    public function cursor($data = null)
    {
        if (!is_null($data)) {
            $this->parsePkWhere($data);
        }

        $this->options['data'] = $data;

        $connection = $this->connection;

        return $connection->cursor($this);
    }

    /**
     * Note: 分批处理返回的数据
     * Date: 2023-04-22
     * Time: 14:17
     * @param int $count 每次处理的数量
     * @param \Closure $callback 回调方法
     * @param string|array $column 要根据查询的字段
     * @param string $sort 排序方法
     * @return bool
     */
    public function chunk(int $count, \Closure $callback, $column = null, string $sort = 'asc')
    {
        $options = $this->getOptions();
        $column = $column ?: $this->getPk();

        if (isset($options['order'])) {
            unset($options['order']);
        }

        $query = $this->options($options)->limit($count);

        if (is_string($column)) {
            if (strpos($column, '.')) {
                [$alias, $key] = explode('.', $column);
            } else {
                $key = $column;
            }

            $query->order($column, $sort);
        } else {
            $query->order($column);
        }

        $resultSet = $query->select();

        while (count($resultSet) > 0) {
            if (call_user_func($callback, $resultSet) === false) {
                return false;
            }

            $newQuery->options($options)->limit($count);

            if (is_string($column)) {
                $end = $resultSet->pop();
                $lastId = $end[$key] ?? 'id';
                $newQuery->order($column, $sort);
            } else {
                $newQuery->order($column);
            }
            $resultSet = $newQuery->where($key, strtolower($sort) == 'asc' ? '>' : '<', $lastId);
        }

        return true;
    }
}