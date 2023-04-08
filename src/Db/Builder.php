<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

use Enna\Orm\Contract\ConnectionInterface;

abstract class Builder
{
    /**
     * Connection对象
     * @var ConnectionInterface
     */
    protected $connection;

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
     * @param bool $distinct
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

    protected function parseForce()
    {

    }

    public function parseJoin()
    {

    }

    public function parseWhere()
    {

    }

    public function parseGroup()
    {

    }

    public function parseHaving()
    {

    }

    public function parseUnion()
    {

    }

    public function parseOrder()
    {

    }

    public function parseLimit()
    {

    }

    public function parseLock()
    {

    }

    public function parseComment()
    {

    }

    protected function parseData()
    {

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