<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Builder;

use Enna\Orm\Db\Builder;
use Enna\Orm\Db\Query;
use Enna\Orm\Db\Raw;
use Enna\Orm\Db\Exception\DbException;

/**
 * MySQL解析类
 * Class Mysql
 * @package Enna\Orm\Db\Builder
 */
class Mysql extends Builder
{
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
        'parseRegexp' => ['REGEXP', 'NOT REGEXP'],
        'parseFindInSet' => ['FIND IN SET'],
    ];

    /**
     * select SQL表达式
     * @var string
     */
    protected $selectSql = 'SELECT%DISTINCT%%EXTRA% %FIELD% FROM %TABLE%%PARTITION%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%UNION%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * insert SQL表达式
     * @var string
     */
    protected $insertSql = '%INSERT%%EXTRA% INTO %TABLE%%PARTITION% SET %SET% %DUPLICATE%%COMMENT%';

    /**
     * insertAll SQL表达式
     * @var string
     */
    protected $insertAllSql = '%INSERT%%EXTRA% INTO %TABLE%%PARTITION% (%FIELD%) VALUES %DATA% %DUPLICATE%%COMMENT%';

    /**
     * update SQL表达式
     * @var string
     */
    protected $updateSql = 'UPDATE%EXTRA% %TABLE%%PARTITION% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * delete SQL表达式
     * @var string
     */
    protected $deleteSql = 'DELETE%EXTRA% FROM %TABLE%%PARTITION%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * Note: 生成查询SQL
     * Date: 2023-04-28
     * Time: 9:58
     * @param Query $query 查询对象
     * @param bool $one 是否仅获取一个记录
     * @return string
     */
    public function select(Query $query, bool $one = false)
    {
        $options = $query->getOptions();

        return str_replace(
            ['%DISTINCT%', '%EXTRA%', '%FIELD%', '%TABLE%', '%PARTITION%', '%FORCE%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%UNION%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseDistinct($query, $options['distinct']),
                $this->parseExtra($query, $options['extra']),
                $this->parseField($query, $options['field'] ?? '*'),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                $this->parseForce($query, $options['force']),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseGroup($query, $options['group']),
                $this->parseHaving($query, $options['having']),
                $this->parseUnion($query, $one ? '1' : $options['union']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->selectSql
        );
    }

    /**
     * Note: 生成insert SQL
     * Date: 2023-04-28
     * Time: 10:00
     * @param Query $query 查询对象
     * @return string
     */
    public function insert(Query $query)
    {
        $options = $query->getOptions();

        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $set = [];
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }

        return str_replace(
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%PARTITION% ', '%SET%', '%DUPLICATE%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                explode(' , ', $set),
                $this->parseDuplicate($query, $options['duplicate']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertSql
        );
    }

    /**
     * Note: 生成insert all SQL
     * Date: 2023-04-28
     * Time: 15:36
     * @param Query $query
     * @param array $dataSet
     * @param bool $replace
     */
    public function insertAll(Query $query, array $dataSet, bool $replace = false)
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
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%PARTITION%', '%FIELD%', '%DATA%', '%DUPLICATE%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parseTable($query, $options['partition']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseDuplicate($query, $options['duplicate']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /**
     * Note: update SQL
     * Date: 2023-04-28
     * Time: 15:54
     * @param Query $query 查询对象
     * @return string
     */
    public function update(Query $query)
    {
        $options = $query->getOptions();

        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $set = [];
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }

        return str_replace(
            ['%EXTRA%', '%TABLE%', '%PARTITION%', '%JOIN%', '%SET%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                $this->parseJoin($query, $options['json']),
                implode(' , ', $set),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->updateSql,
        );
    }

    /**
     * Note: 生成delete SQL
     * Date: 2023-04-28
     * Time: 16:57
     * @param Query $query 查询对象
     * @return string
     */
    public function delete(Query $query)
    {
        $options = $this->getOptions();

        return str_replace(
            ['%EXTRA%', '%TABLE%', '%PARTITION%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($options['using']) . ' ' : '',
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

    /**
     * Note: 正则查询
     * Date: 2023-04-28
     * Time: 18:05
     * @param Query $query 查询对象
     * @param string $key 字段
     * @param string $exp 表达式
     * @param mixed $value 值
     * @param string $field JSON解析后的字段
     * @return string
     */
    protected function parseRegexp(Query $query, string $key, string $exp, $value, string $field)
    {
        if ($value instanceof Raw) {
            $value = $this->parseRaw($query, $value);
        }

        return $key . ' ' . $exp . ' ' . $value;
    }

    /**
     * Note: find_in_set 查询
     * Date: 2023-04-28
     * Time: 18:05
     * @param Query $query 查询对象
     * @param string $key 字段
     * @param string $exp 表达式
     * @param mixed $value 值
     * @param string $field JSON解析后的字段
     * @return string
     */
    protected function parseFindInSet(Query $query, string $key, string $exp, $value, string $field)
    {
        if ($value instanceof Raw) {
            $value = $this->parseRaw($query, $value);
        }

        return 'FIND_IN_SET(' . $value . ', ' . $key . ')';
    }

    /**
     * Note: 字段处理:field,table,group,order
     * Date: 2023-04-27
     * Time: 16:37
     * @param Query $query 查询对象
     * @param mixed $key 字段名
     * @param bool $strict 严格检测
     * @return string
     */
    protected function parseKey(Query $query, $key, bool $strict = false)
    {
        if (is_int($key)) {
            return (int)$key;
        } elseif ($key instanceof Raw) {
            return $this->parseRaw($key);
        }

        $key = trim($key);

        if (strpos($key, '->>') && false === strpos($key, '(')) {
            [$field, $name] = explode('->>', $key, 2);

            return $this->parseKey($query, $field, true) . '->>\'$' . (strpos($name, '[') === 0 ? '' : '.') . str_replace('->>', '.', $name) . '\'';
        } elseif (strpos($key, '->') && strpos($key, '(') === false) {
            [$field, $name] = explode('->', $key, 2);

            return 'json_extract(' . $this->parseKey($query, $field, true) . ', \'$' . (strpos($name, '[') === 0 ? '' : '.') . str_replace('->', '.', $name) . '\')';
        } elseif (strpos($key, '.') && !preg_match('/[,\'\"\(\)`\s]]/', $key)) {
            [$table, $key] = explode('.', $key, 2);

            $alias = $query->getOptions('alias');

            if ($table == '__TABLE__') {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }

            if (isset($alias[$table])) {
                $table = $alias[$table];
            }
        }

        if ($strict && preg_match('/^[\w\.\*]+$/', $key)) {
            throw new DbException('not support data:' . $key);
        }

        if ($key != '*' && !preg_match('/[,\'\"\(\)`\s]]/', $key)) {
            $key = '`' . $key . '`';
        }

        if (isset($table)) {
            $key = '`' . $table . '`' . $key;
        }

        return $key;
    }

    /**
     * Note: 随机排序
     * Date: 2023-04-28
     * Time: 18:15
     * @param Query $query 查询对象
     * @return string
     */
    protected function parseRand(Query $query)
    {
        return 'rand()';
    }

    /**
     * Note: partition 分析
     * Date: 2023-04-27
     * Time: 9:49
     * @param Query $query 查询对象
     * @param string|array $partition 分区
     * @return string
     */
    protected function parsePartition(Query $query, $partition)
    {
        if ($partition == '') {
            return '';
        }

        if (is_string($partition)) {
            $partition = explode(',', $partition);
        }

        return 'PARTITION (' . implode(' , ', $partition) . ')';
    }

    /**
     * Note: ON DUPLICATE KEY UPDATE分析:用于在插入数据时,出现重复的关键字(通常为唯一索引或主键),则执行更新操作而不是插入新纪录
     * Date: 2023-04-28
     * Time: 11:43
     * @param Query $query 查询对象
     * @param mixed $duplicate uplidate条件
     * @return string
     */
    protected function parseDuplicate(Query $query, $duplicate)
    {
        if ($duplicate == '') {
            return '';
        }

        if ($duplicate instanceof Raw) {
            return 'ON DUPLIDATE KEY UPDATE ' . $this->parseRaw($duplicate) . ' ';
        }

        if (is_string($duplicate)) {
            $duplicate = explode(',', $duplicate);
        }

        $updates = [];
        foreach ($duplicate as $key => $val) {
            if (is_null($key)) {
                $val = $this->parseKey($query, $val);
                $updates[] = $val . ' =VALUES(' . $val . ')';
            } elseif ($val instanceof Raw) {
                $updates[] = $this->parseKey($query, $key) . ' = ' . $this->parseRaw($query, $val);
            } else {
                $name = $query->bindValue($val, $this->getConnection()->getFieldBindType($key));
                $updates[] = $this->parseKey($query, $key) . ' = : ' . $name;
            }
        }

        return 'ON DUPLICATE KEY UPDATE ' . implode(' , ', $updates) . ' ';
    }
}