<?php
declare(strict_types=1);

namespace Enna\Orm\Db;

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
}