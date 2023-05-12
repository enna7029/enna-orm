<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Framework\Exception;
use Enna\Orm\Contract\ConnectionInterface;
use Closure;
use Enna\Orm\Db\Exception\DbException;
use PDO;
use Predis\Command\Redis\QUIT;

abstract class Builder
{
    /**
     * Connection对象
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * 查询表达式映射
     * @var array
     */
    protected $exp = [
        'NOTLIKE' => 'NOT LIKE',
        'NOTIN' => 'NOT IN',
        'NOTBETWEEN' => 'NOT BETWEEN',
        'NOTEXISTS' => 'NOT EXISTS',
        'NOTNULL' => 'NOT NULL',
        'NOTBETWEEN TIME' => 'NOT BETWEEN TIME',
    ];

    /**
     * 查询表达式解析
     * @var array
     */
    protected $parser = [
        'parseCompare' => ['=', '<>', '>', '>=', '<', '<='],
        'parseLIke' => ['LIKE', 'NOT LIKE'],
        'parseBetween' => ['BETWEEN', 'NOT BETWEEN'],
        'parseIn' => ['IN', 'NOT IN'],
        'parseExp' => ['EXP'],
        'parseNull' => ['NULL', 'NOT NULL'],
        'parseBetweenTime' => ['BETWEEN TIME', 'NOT BETWEEN TIME'],
        'parseTime' => ['< TIME', '> TIME', '<= TIME', '<=TIME'],
        'parseExists' => ['EXISTS', 'NOT EXISTS'],
        'parseColumn' => ['COLUMN'],
    ];

    /**
     * select SQL表达式
     * @var string
     */
    protected $selectSql = 'SELECT%DISTINCT%%EXTRA% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * insert SQL表达式
     * @var string
     */
    protected $insertSql = '%INSERT%%EXTRA% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';

    /**
     * insertAll SQL表达式
     * @var string
     */
    protected $insertAllSql = '%INSERT%%EXTRA% INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';

    /**
     * update SQL表达式
     * @var string
     */
    protected $updateSql = 'UPDATE%EXTRA% %TABLE% SET %SET%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * delete SQL表达式
     * @var string
     */
    protected $deleteSql = 'DELETE%EXTRA% FROM %TABLE%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Note: 获取当前连接对象
     * Date: 2023-04-26
     * Time: 14:45
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Note: 注册查询表达式解析
     * Date: 2023-04-26
     * Time: 14:46
     * @param string $name 解析方法
     * @param array $parser 匹配的表达式
     * @return $this
     */
    public function bindParser(string $name, array $parser)
    {
        $this->parser[$name] = $parser;

        return $this;
    }

    /**
     * Note: table分析
     * Date: 2023-03-30
     * Time: 14:54
     * @param Query $query 查询对象
     * @param mixed $tables 表名
     * @return string
     */
    protected function parseTable(Query $query, $tables)
    {
        $item = [];
        $options = $query->getOptions();

        foreach ((array)$tables as $key => $table) {
            if ($table instanceof Raw) {
                $item[] = $this->parseRaw($query, $table);
            } elseif (!is_numeric($key)) {
                $item[] = $this->parseKey($query, $key) . ' ' . $this->parseKey($query, $table);
            } elseif (isset($options['alias'][$key])) {
                $item[] = $this->parseKey($query, $key) . ' ' . $this->parseKey($query, $options['alias'][$table]);
            } else {
                $item[] = $this->parseKey($query, $table);
            }
        }

        return implode(',', $item);
    }

    /**
     * Note: distinct分析
     * Date: 2023-03-30
     * Time: 16:42
     * @param Query $query 查询对象
     * @param bool $distinct 是否返回唯一值
     * @return string
     */
    protected function parseDistinct(Query $query, bool $distinct = false)
    {
        return !empty($distinct) ? 'DISTINCT ' : '';
    }

    /**
     * Note:
     * Date: 2023-03-30
     * Time: 16:43
     * @param Query $query 查询对象
     * @param string $extra 额外参数
     * @return string
     */
    protected function parseExtra(Query $query, string $extra)
    {
        return preg_match('/^[\w]+$/i', $extra) ? ' ' . $extra : '';
    }

    /**
     * Note: field分析
     * Date: 2023-03-30
     * Time: 17:33
     * @param Query $query 查询对象
     * @param mixed $fields 字段名
     * @return string
     */
    protected function parseField(Query $query, $fields)
    {
        if (is_array($fields)) {
            $array = [];

            foreach ($fields as $key => $field) {
                if ($fields instanceof Raw) {
                    $array[] = $this->parseKey($query, $field);
                } elseif (!is_numeric($key)) {
                    $array[] = $this->parseKey($query, $key) . ' AS ' . $this->parseKey($query, $field, true);
                } else {
                    $array[] = $this->parseKey($query, $field);
                }
            }

            $fieldsStr = implode(',', $array);
        } else {
            $fieldsStr = '*';
        }

        return $fieldsStr;
    }

    /**
     * Note: index 索引提示分析,可以在操作中指定需要强制使用的索引
     * Date: 2023-04-27
     * Time: 12:09
     * @param Query $query 查询对象
     * @param mixed $index 索引名称
     * @return string
     */
    protected function parseForce(Query $query, $index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = implode(',', $index);
        }

        return sprintf(" FORCE INDEX (%s) ", $index);
    }

    /**
     * Note: join分析
     * Date: 2023-04-27
     * Time: 14:53
     * @param Query $query 查询对象
     * @param array $join JOIN条件
     * @return string
     */
    protected function parseJoin(Query $query, array $join)
    {
        $joinStr = '';

        foreach ($join as $item) {
            [$table, $type, $on] = $item;

            if (strpos($on, '=')) {
                [$val1, $val2] = explode('=', $on, 2);

                $condition = $this->parseKey($query, $val1) . '=' . $this->parseKey($query, $val2);
            } else {
                $condition = $on;
            }

            $table = $this->parseTable($query, $table);

            $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . $condition;
        }

        return $joinStr;
    }

    /**
     * Note: where分析
     * Date: 2023-04-27
     * Time: 17:22
     * @param Query $query 查询对象
     * @param mixed $where 查询条件
     * @return string
     */
    protected function parseWhere(Query $query, array $where)
    {
        $options = $query->getOptions();
        $whereStr = $this->buildWhere($query, $where);

        if (!empty($options['soft_delete'])) {
            [$field, $condition] = $options['soft_delete'];

            $binds = $query->getFieldsBindType();
            $whereStr = $whereStr ? '( ' . $whereStr . ' ) AND ' : '';
            $whereStr = $whereStr . $this->parseWhereItem($query, $field, $condition, $binds);
        }

        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * Note: 生成where:1
     * Date: 2023-04-27
     * Time: 17:29
     * @param Query $query 查询对象
     * @param array $where 查询条件
     * @return string
     */
    protected function buildWhere(Query $query, array $where)
    {
        if (empty($where)) {
            $where = [];
        }

        $whereStr = '';


        $binds = $query->getFieldsBindType();
        foreach ($where as $logic => $val) {
            $str = $this->parseWhereLogic($query, $logic, $val, $binds);

            $whereStr .= empty($str) ? substr(implode(' ', $str), strlen($logic) + 1) : implode(' ', $str);
        }

        return $whereStr;
    }

    /**
     * Note: 生成where:2
     * Date: 2023-05-04
     * Time: 14:30
     * @param Query $query 查询对象
     * @param string $logic Logic
     * @param array $val 查询条件
     * @param array $binds 参数绑定
     * @return array
     */
    protected function parseWhereLogic(Query $query, string $logic, array $val, array $binds = [])
    {
        $where = [];
        foreach ($val as $item) {
            if ($item instanceof Raw) {
                $where[] = ' ' . $logic . ' ( ' . $this->parseRaw($query, $item) . ' ) ';
                continue;
            }

            //获取条件中的第一个字段
            if (is_array($item)) {
                if (key($item) !== 0) {
                    throw new DbException('where express error:' . var_export($item, true));
                }
                $field = array_shift($item);
            } elseif ($item === true) {
                $where[] = ' ' . $logic . ' 1 ';
            } elseif (!($item instanceof Closure)) {
                throw new DbException('where express error:' . var_export($item, true));
            }

            if ($item instanceof Closure) {
                $whereClosureStr = $this->parseClosureWhere($query, $item, $logic);
                if ($whereClosureStr) {
                    $where[] = $whereClosureStr;
                }
            } elseif (is_array($field)) {
                $where[] = $this->parseMultiWhere($query, $item, $field, $logic, $binds);
            } elseif (strpos($field, '|')) {
                $where[] = $this->parseFieldsOr($query, $item, $field, $logic, $binds);
            } elseif (strpos($field, '&')) {
                $where[] = $this->parseFieldsAnd($query, $item, $field, $logic, $binds);
            } else {
                $field = is_string($field) ? $field : '';
                $where[] = ' ' . $logic . ' ' . $this->parseWhereItem($query, $field, $item, $binds);
            }
        }

        return $where;
    }

    /**
     * Note: 闭包查询
     * Date: 2023-05-04
     * Time: 15:05
     * @param Query $query 查询对象
     * @param Closure $value 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return string
     */
    protected function parseClosureWhere(Query $query, Closure $value, string $logic)
    {
        $newQuery = $query->newQuery();
        $value($newQuery);
        $whereClosure = $this->buildWhere($newQuery, $newQuery->getOptions('where') ?: []);

        if (!empty($whereClosure)) {
            $query->bind($newQuery->getBind(false));
            $where = ' ' . $logic . ' ( ' . $whereClosure . ' ) ';
        }

        return $where ?? '';
    }

    /**
     * Note: 复合条件查询
     * Date: 2023-05-04
     * Time: 15:19
     * @param Query $query 查询对象
     * @param mixed $value 查询条件
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @param array $binds 参数绑定
     * @return string
     */
    protected function parseMultiWhere(Query $query, $value, $field, string $logic, array $binds)
    {
        array_unshift($value, $field);

        $where = [];
        foreach ($value as $item) {
            $where[] = $this->parseWhereItem($query, array_shift($item), $item, $binds);
        }

        return ' ' . $logic . ' ( ' . implode(' AND ', $where) . ' )';
    }

    /**
     * Note: 不同字段使用相同条件查询:or逻辑
     * Date: 2023-05-04
     * Time: 16:40
     * @param Query $query 查询对象
     * @param mixed $value 查询条件
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @param array $binds 参数绑定
     * @return string
     */
    protected function parseFieldsOr(Query $query, $value, string $field, string $logic, array $binds)
    {
        $item = [];

        foreach (explode('|', $field) as $k) {
            $item[] = $this->parseWhereItem($query, $k, $value, $binds);
        }

        return ' ' . $logic . ' ( ' . implode(' OR ', $where) . ' )';
    }

    /**
     * Note: 不同字段使用相同条件查询:and逻辑
     * Date: 2023-05-04
     * Time: 16:40
     * @param Query $query 查询对象
     * @param mixed $value 查询条件
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @param array $binds 参数绑定
     * @return string
     */
    protected function parseFieldsAnd(Query $query, $value, string $field, string $logic, array $binds)
    {
        $item = [];

        foreach (explode('&', $field) as $k) {
            $item[] = $this->parseWhereItem($query, $k, $value, $binds);
        }

        return ' ' . $logic . ' ( ' . implode(' AND ', $where) . ' )';
    }


    /**
     * Note: where子单元查询
     * Date: 2023-05-04
     * Time: 15:30
     * @param Query $query 查询对象
     * @param mixed $field 查询字段
     * @param array $condition 查询条件
     * @param array $binds 参数绑定
     * @return string
     */
    protected function parseWhereItem(Query $query, $field, array $condition, array $binds)
    {
        $key = $field ? $this->parseKey($query, $field, true) : '';

        [$exp, $value] = $condition;
        if (!is_string($exp)) {
            throw new DbException('where express error:' . var_export($exp, true));
        }

        $exp = strtoupper($exp);
        if (isset($this->exp[$exp])) {
            $exp = $this->exp[$exp];
        }

        $bindType = $binds[$field] ?? PDO::PARAM_STR;

        if (is_scalar($value) && !in_array($exp, ['EXP', 'NOT NULL', 'NULL', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN']) && strpos($exp, 'TIME') === false) {
            $name = $query->bindValue($value, $bindType);
            $value = ':' . $name;
        }

        foreach ($this->parser as $fun => $parse) {
            if (in_array($exp, $parse)) {
                return $this->$fun($query, $key, $exp, $value, $field, $bindType, $val[2] ?? 'AND');
            }
        }

        throw new DbException('where express error:' . $exp);
    }

    /**
     * Note: 比较运算符查询
     * Date: 2023-05-04
     * Time: 17:22
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseCompare(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        if (is_array($value)) {
            throw new DbException('where express error:' . var_export($value, true));
        }

        if ($value instanceof Closure) {
            $value = $this->parseClosure($query, $value);
        }

        if ($exp == '=' && is_null($value)) {
            return $key . ' IS NULL';
        }

        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * Note: 模糊查询
     * Date: 2023-05-04
     * Time: 17:31
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @param strign $logic 查询逻辑 and|or
     * @return string
     */
    protected function parseLike(Query $query, string $key, string $exp, $value, $field, int $bindType, string $logic)
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $name = $query->bindValue($item, $bindType);
                $array[] = $key . ' ' . $exp . ' :' . $name;
            }
            $whereStr = '(' . implode(' ' . strtoupper($logic) . ' ', $array) . ')';
        } else {
            $whereStr = $key . ' ' . $exp . ' ' . $value;
        }

        return $whereStr;
    }

    /**
     * Note: 范围查询
     * Date: 2023-05-04
     * Time: 17:48
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseBetween(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        $data = is_array($value) ? $value : explode(',', $value);

        $min = $query->bindValue($data[0], $bindType);
        $max = $query->bindValue($data[1], $bindType);

        return $key . ' ' . $exp . ' :' . $min . ' AND :' . $max . ' ';
    }

    /**
     * Note: in查询
     * Date: 2023-05-04
     * Time: 17:53
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseIn(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        if ($value instanceof Closure) {
            $value = $this->parseClosure($query, $value, false);
        } elseif ($value instanceof Raw) {
            $value = $this->parseRaw($query, $value);
        } else {
            $value = array_unique(is_array($value) ? $value : explode(',', $value));

            if (count($value) === 0) {
                return $exp == 'IN' ? '0=1' : '1=1';
            }

            $array = [];
            foreach ($value as $item) {
                $name = $query->bindValue($query, $item);
                $array[] = ':' . $name;
            }

            if (count($array) == 1) {
                return $key . ($exp == 'IN' ? '=' : '<>') . $array[0];
            } else {
                $value = implode(',', $array);
            }
        }

        return $key . ' ' . $exp . ' (' . $value . ')';
    }

    /**
     * Note: 表达式查询
     * Date: 2023-05-04
     * Time: 18:12
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseExp(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        return '( ' . $key . ' ' . $this->parseRaw($query, $value) . ' )';
    }

    /**
     * Note: NULL查询
     * Date: 2023-05-04
     * Time: 18:15
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseNull(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        return $key . ' IS ' . $exp;
    }

    /**
     * Note: 时间范围查询
     * Date: 2023-05-04
     * Time: 18:25
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseBetweenTime(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return $key . ' ' . substr($exp, 0, -4)
            . $this->parseDateTime($query, $value[0], $field, $bindType)
            . ' AND '
            . $this->parseDateTime($query, $value[1], $field, $bindType);
    }

    /**
     * Note: 时间比较查询
     * Date: 2023-05-04
     * Time: 18:25
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseTime(Query $query, string $key, string $exp, $value, $field, int $bindType)
    {
        return $key . ' ' . substr($exp, 0, -4) . ' ' . $this->parseDateTime($query, $value, $field, $bindType);
    }

    /**
     * Note: 日期时间条件解析
     * Date: 2023-05-05
     * Time: 15:57
     * @param Query $query 查询对象
     * @param mixed $value 查询条件
     * @param string $key 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseDateTime(Query $query, $value, string $key, int $bindType)
    {
        $options = $query->getOptions();

        $type = $query->getFieldType();
        if ($type) {
            if (is_string($value)) {
                $value = strtotime($value) ?: $value;
            }

            if (is_int($value)) {
                if (preg_match('/(datetime|timestamp)/is', $type)) {
                    $value = date('Y-m-d H:i:s', $value);
                } elseif (preg_match('/(date)/is', $type)) {
                    $value = date('Y-m-d', $value);
                }
            }
        }

        $name = $query->bindValue($value, $bindType);

        return ':' . $name;
    }

    /**
     * Note: Exists查询
     * Date: 2023-05-04
     * Time: 18:17
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseExists(Query $query, string $key, string $exp, $value, $field, int $bindType, string $logic)
    {
        if ($value instanceof Closure) {
            $value = $this->parseClosure($query, $value, false);
        } elseif ($value instanceof Raw) {
            $value = $this->parseRaw($query, $value);
        } else {
            throw new DbException('where express error:' . $value);
        }

        return $exp . ' ( ' . $value . ' )';
    }

    /**
     * Note:
     * Date: 2023-05-04
     * Time: 18:19
     * @param Query $query 查询对象
     * @param string $key 查询字段
     * @param string $exp 查询表达式
     * @param mixed $value 查询条件
     * @param string $field 原始查询字段
     * @param int $bindType 字段类型
     * @return string
     */
    protected function parseColumn(Query $query, string $key, string $exp, $value, $field, int $bindType, string $logic)
    {
        [$op, $field] = $value;

        if (!in_array(trim($op), ['=', '<>', '>', '>=', '<', '<='])) {
            throw new DbException('where express error:' . var_export($value, true));
        }

        return '( ' . $key . ' ' . $op . ' ' . $this->parseKey($query, $field, true) . ' )';
    }

    /**
     * Note: group分析
     * Date: 2023-04-27
     * Time: 17:35
     * @param Query $query 查询对象
     * @param mixed $group group条件
     * @return string
     */
    protected function parseGroup(Query $query, $group)
    {
        if (empty($group)) {
            return '';
        }

        if (is_string($group)) {
            $group = explode(',', $group);
        }

        $val = [];
        foreach ($group as $key) {
            $val[] = $this->parseKey($query, $key);
        }

        return ' GROUP BY ' . implode(',', $val);
    }

    /**
     * Note: having分析
     * Date: 2023-04-27
     * Time: 17:38
     * @param Query $query 查询对象
     * @param string $having having条件
     * @return string
     */
    protected function parseHaving(Query $query, string $having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * Note: union分析
     * Date: 2023-04-27
     * Time: 17:40
     * @param Query $query 查询对象
     * @param array $union union条件
     * @return string
     */
    protected function parseUnion(Query $query, array $union)
    {
        if (empty($union)) {
            return '';
        }

        $type = $union['type'];
        unset($union['type']);

        $sql = [];
        foreach ($union as $item) {
            if ($item instanceof Closure) {
                $sql[] = $type . ' ' . $this->parseClosure($query, $item);
            } elseif (is_string($item)) {
                $sql[] = $type . ' ( ' . $item . ' ) ';
            }
        }

        return ' ' . implode(' ', $sql);
    }

    /**
     * Note: 闭包子查询
     * Date: 2023-04-27
     * Time: 17:56
     * @param Query $query 查询对象
     * @param Closure $callback 闭包
     * @param bool $show 是否加括号
     * @return string
     */
    protected function parseClosure(Query $query, Closure $callback, bool $show = true)
    {
        $newQuery = $query->newQuery()->removeOption();

        $callback($newQuery);

        return $newQuery->buildSql($show);
    }

    /**
     * Note: order分析
     * Date: 2023-04-27
     * Time: 18:15
     * @param Query $query 查询对象
     * @param array $order 排序条件
     * @return string
     */
    protected function parseOrder(Query $query, array $order)
    {
        $array = [];
        foreach ($order as $key => $val) {
            if ($val instanceof Raw) {
                $array[] = $this->parseRaw($query, $val);
            } elseif (is_array($val) && preg_match('/^[\w\.]+&/', $key)) {
                $array[] = $this->parseOrderField($query, $key, $val);
            } elseif ($val == '[rand]') {
                $array[] = $this->parseRand($val);
            } elseif (is_string($val)) {
                if (is_numeric($key)) {
                    [$key, $sort] = explode(' ', $val . ' ');
                } else {
                    $sort = $val;
                }

                if (preg_match('/^[\w\.]+&/', $key)) {
                    $sort = strtoupper($sort);
                    $sort = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';
                    $array[] = $this->parseKey($query, $key, true) . $sort;
                } else {
                    throw new DbException('order express error:' . $key);
                }
            }
        }

        return empty($array) ? '' : ' ORDER BY ' . implode(',', $array);
    }

    /**
     * Note: orderField分析
     * Date: 2023-04-28
     * Time: 18:25
     * @param Query $query 查询对象
     * @param string $key 排序字段
     * @param string $val 排序规则
     * @return string
     */
    protected function parseOrderField(Query $query, string $key, string $val)
    {
        if (isset($val['sort'])) {
            $sort = $val['sort'];
            unset($sort);
        } else {
            $sort = '';
        }

        $sort = strtoupper($sort);
        $sort = in_array($sort, ['ASC', 'DESC'], true) ? ' ' . $sort : '';
        $bind = $query->getFieldsBindType();

        foreach ($val as $k => $item) {
            $val[$k] = $this->parseDataBind($query, $key, $item, $bind);
        }

        return 'field(' . $this->parseKey($query, $key, true) . ',' . implode(',', $val) . ')' . $sort;
    }

    /**
     * Note: limit分析
     * Date: 2023-04-27
     * Time: 18:36
     * @param Query $query 查询对象
     * @param string $limit limit条件
     * @return string
     */
    protected function parseLimit(Query $query, string $limit)
    {
        return !empty($limit) ? ' LIMIT ' . $limit : '';
    }

    /**
     * Note: lock分析
     * Date: 2023-04-27
     * Time: 18:40
     * @param Query $query 查询对象
     * @param bool|string $lock 是否上锁
     * @return string
     */
    protected function parseLock(Query $query, $lock = false)
    {
        if (is_bool($lock)) {
            return $lock ? ' FOR UPDATE ' : '';
        }

        if (is_string($lock) && !empty($lock)) {
            return ' ' . trim($lock) . ' ';
        } else {
            return '';
        }
    }

    /**
     * Note: comment分析
     * Date: 2023-04-27
     * Time: 18:43
     * @param Query $query 查询对象
     * @param string $comment comment条件
     * @return string
     */
    protected function parseComment(Query $query, string $comment)
    {
        if (strpos($comment, '*/')) {
            $comment = strstr($comment, '*/', true);
        }

        return !empty($comment) ? ' /* ' . $comment . ' */ ' : '';
    }

    /**
     * Note: data分析
     * Date: 2023-04-28
     * Time: 14:19
     * @param Query $query 查询对象
     * @param array $data 数据
     * @param array $fields 字段信息
     * @param array $bind 参数绑定
     * @return array
     */
    protected function parseData(Query $query, array $data = [], array $fields = [], array $bind = [])
    {
        if (empty($data)) {
            return [];
        }

        $options = $query->getOptions();

        if (empty($bind)) {
            $bind = $query->getFieldsBindType();
        }

        if (empty($fields)) {
            if (empty($options['field']) || $options['field'] == '*') {
                $fields = array_keys($bind);
            } else {
                $fields = $options['field'];
            }
        }

        $result = [];
        foreach ($data as $key => $val) {
            $item = $this->parseKey($query, $key, true);

            if ($val instanceof Raw) { //原生
                $result[$item] = $this->parseRaw($query, $val);
                continue;
            } elseif (!is_scalar($val) && in_array($key, (array)$options['json'])) { //JSON
                $val = json_encode($val);
            }

            if (strpos($key, '->') !== false) { //json
                [$key, $name] = explode('->', $key, 2);
                $item = $this->parseKey($query, $key);
                $result[$item] = 'json_set(' . $item . ', \'$.' . $name . '\',' . $this->parseDataBind($query, $key . '->' . $name, $val, $bind) . ')';
            } elseif (strpos($key, '.') === false && !in_array($key, $fields, true)) { //严格检查
                if ($options['strict']) {
                    throw new DbException('fields not exists:[' . $key . ']');
                }
            } elseif (is_null($val)) { //默认为空
                $result[$item] = 'NULL';
            } elseif (is_array($val) && !empty($val) && is_string($val[0])) { //inc|dec
                switch (strtoupper($val[0])) {
                    case 'INC':
                        $result[$item] = $item . ' + ' . floatval($val[1]);
                        break;
                    case 'DEC':
                        $result[$item] = $item . ' - ' . floatval($val[1]);
                        break;
                }
            } elseif (is_scalar($val)) { //标量类型
                $result[$item] = $this->parseDataBind($query, $key, $val, $bind);
            }
        }

        return $result;
    }

    /**
     * Note: 数据绑定处理
     * Date: 2023-04-28
     * Time: 15:27
     * @param Query $query 查询对象
     * @param string $key 字段名
     * @param mixed $data 数据
     * @param array $bind 绑定数据
     * @return string
     */
    protected function parseDataBind(Query $query, string $key, $data, array $bind = [])
    {
        if ($data instanceof Raw) {
            return $this->parseRaw($query, $data);
        }

        $name = $query->bindValue($data, $bind[$key] ?? PDO::STR);

        return ':' . $name;
    }

    /**
     * Note: 分析Raw对象
     * Date: 2023-03-30
     * Time: 15:54
     * @param Query $query 查询对象
     * @param Raw $raw Raw对象
     * @return string
     */
    protected function parseRaw(Query $query, Raw $raw)
    {
        $sql = $raw->getValue();
        $bind = $raw->getBind();

        if ($bind) {
            $query->bindParams($sql, $bind);
        }

        return $sql;
    }

    /**
     * Note: 字段名分析
     * Date: 2023-03-30
     * Time: 16:40
     * @param Query $query 查询对象
     * @param mixed $key 字段名
     * @param bool $strict 严格检测
     */
    public function parseKey(Query $query, $key, bool $strict = false)
    {
        return $key;
    }

    /**
     * Note: 生成查询SQL
     * Date: 2023-03-30
     * Time: 11:37
     * @param Query $query 查询对象
     * @param bool $one 是否仅取一个记录
     * @return string
     */
    public function select(Query $query, bool $one = false)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%DISTINCT%', '%EXTRA%', '%FIELD%', '%FORCE%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%UNION%', ' %ORDER%', ' %LIMIT%', '%LOCK%', ' %COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseDistinct($query, $options['distinct']),
                $this->parseExtra($query, $options['extra']),
                $this->parseField($query, $options['field'] ?? '*'),
                $this->parseForce($query, $options['force']),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseGroup($query, $options['group']),
                $this->parseHaving($query, $options['having']),
                $this->parseUnion($query, $options['union']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $option['limit']),
                $this->parseLock($query, $option['lock']),
                $this->parseComment($query, $option['comment']),
            ],
            $this->selectSql
        );
    }

    /**
     * Note: 生成插入SQL
     * Date: 2023-03-31
     * Time: 14:46
     * @param Query $query 查询对象
     * @return string
     */
    public function insert(Query $query)
    {
        $options = $query->getOptions();

        $data = $this->parseData($query, $option['data']);
        if (empty($data)) {
            return '';
        }

        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $option['extra']),
                $this->parseTable($query, $options['table']),
                implode(',', $fields),
                implode(',', $values),
                $this->parseComment($query, $option['comment']),
            ],
            $this->insertSql
        );
    }

    /**
     * Note: 生成insert all SQL
     * Date: 2023-05-05
     * Time: 16:29
     * @param Query $query 查询对象
     * @param array $dataSet 数据集
     * @return string
     */
    public function insertAll(Query $query, array $dataSet)
    {
        $options = $query->getOptions();

        $bind = $query->getFieldsBindType();

        if (empty($options['field']) || $options['field'] == '*') {
            $allowFields = array_key($bind);
        } else {
            $allowFields = $options['field'];
        }

        $fields = [];
        $values = [];

        foreach ($dataSet as $data) {
            $data = $this->parseData($query, $data, $allowFields, $bind);

            $values[] = '(' . implode(',', array_values($data)) . ')';

            if (!isset($insertFields)) {
                $insertFields = array_keys($data);
            }
        }

        foreach ($insertFields as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        return str_replace(
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /**
     * Note: 生成inert select 语句
     * Date: 2023-04-03
     * Time: 11:52
     * @param Query $query 查询对象
     * @param array $fields 数据
     * @param string $table 数据表
     * @return string
     */
    public function selectInsert(Query $query, array $fields, string $table)
    {
        foreach ($fields as &$field) {
            $field = $this->parseKey($query, $field, true);
        }

        return 'INSERT INTO ' . $this->parseTable($query, $table) . ' (' . implode(',', $fields) . ') ' . $this->select($query);
    }

    /**
     * Note: 生成update SQL
     * Date: 2023-04-03
     * Time: 12:04
     * @param Query $query 查询对象
     * @return string
     */
    public function update(Query $query)
    {
        $options = $this->getOptions();

        $data = $this->parseData($query, $options['data']);

        if (empty($data)) {
            return '';
        }

        $set = [];
        foreach ($data as $key => $val) {
            $set[] = $key . ' = ' . $val;
        }

        return str_replace(
            ['%TABLE%', '%EXTRA%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $set),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->updateSql
        );
    }

    /**
     * Note: 生成delete SQL
     * Date: 2023-04-03
     * Time: 15:05
     * @param Query $query 查询对象
     * @return string
     */
    public function delete(Query $query)
    {
        $options = $this->getOptions();

        return str_replace(
            ['%TABLE%', '%EXTRA%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($query, $options['using']) . ' ' : '',
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->deleteSql
        );
    }
}