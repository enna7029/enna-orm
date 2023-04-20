<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Db\Raw;

/**
 * 聚合查询
 * Trait AggregateQuery
 * @package Enna\Orm\Db\Concern
 */
trait AggregateQuery
{
    /**
     * Note: 聚合查询
     * Date: 2023-04-10
     * Time: 11:39
     * @param string $aggregate 聚合方法
     * @param string|Raw $field 字段名
     * @param bool $force 强制转换为数字类型
     * @return mixed
     */
    protected function aggregate(string $aggregate, $field, bool $force = false)
    {
        return $this->connection->aggregate($this, $aggregate, $field, $force);
    }

    /**
     * Note: COUNT查询
     * Date: 2023-04-10
     * Time: 11:46
     * @param string|Raw $field 字段名
     * @return int
     */
    public function count(string $field = '*')
    {
        if (!empty($this->options['group'])) {
            $subSql = $this->field('count(' . $field . ') as count')
                ->bind($this->bind)
                ->buildSql();

            $query = $this->newQuery()->table([$subSql => '_group_count_']);
            $count = $query->aggregate('COUNT', '*');
        } else {
            $count = $this->aggregate('COUNT', $field);
        }

        return (int)$count;
    }

    /**
     * Note: SUM查询
     * Date: 2023-04-10
     * Time: 14:44
     * @param string|Raw $field 字段名
     * @return float
     */
    public function sum($field)
    {
        return $this->aggregate('SUM', $field, true);
    }

    /**
     * Note: AVG查询
     * Date: 2023-04-10
     * Time: 14:45
     * @param string|Raw $field 字段名
     * @return float
     */
    public function avg($field)
    {
        return $this->aggregate('AVG', $field, true);
    }

    /**
     * Note: MAX查询
     * Date: 2023-04-10
     * Time: 14:45
     * @param string|Raw $field 字段名
     * @param bool $force 强制转换为数字类型
     * @return mixed
     */
    public function max($field, bool $force = true)
    {
        return $this->aggregate('MAX', $field, $force);
    }

    /**
     * Note: MIN查询
     * Date: 2023-04-10
     * Time: 14:47
     * @param string|Raw $field 字段名
     * @param bool $force 强制转换为数字类型
     * @return mixed
     */
    public function min($field, bool $force = true)
    {
        return $this->aggregate('MIN', $field, $force);
    }
}