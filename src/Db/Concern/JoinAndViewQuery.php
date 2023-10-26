<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Db\Raw;

/**
 * JOIN和VIEW查询
 * Trait JoinAndViewQuery
 * @package Enna\Orm\Db\Concern
 */
trait JoinAndViewQuery
{
    /**
     * Note: 查询SQL组装
     * Date: 2023-04-27
     * Time: 14:28
     * @param mixed $join 关联的表名
     * @param string $condition 条件
     * @param string $type JOIN类型
     * @param array $bind 参数绑定
     * @return $this
     */
    public function join($join, string $condition = null, string $type = 'INNER', array $bind = [])
    {
        $table = $this->getJoinTable($join);

        if (!empty($bind) && $condition) {
            $this->bindParams($condition, $bind);
        }

        $this->options['join'][] = [$table, strtoupper($type), $condition];

        return $this;
    }

    /**
     * Note: LEFT JOIN
     * Date: 2023-04-27
     * Time: 15:28
     * @param mixed $join 关联的表名
     * @param string $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function leftJoin($join, string $condition = null, array $bind = [])
    {
        return $this->join($join, $condition, 'LEFT', $bind);
    }

    /**
     * Note: RIGHT JOIN
     * Date: 2023-04-27
     * Time: 15:28
     * @param mixed $join 关联的表名
     * @param string $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function rightJoin($join, string $condition = null, array $bind = [])
    {
        return $this->join($join, $condition, 'RIGHT', $bind);
    }

    /**
     * Note: FULL JOIN
     * Date: 2023-04-27
     * Time: 15:28
     * @param mixed $join 关联的表名
     * @param string $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function fullJoin($join, string $condition = null, array $bind = [])
    {
        return $this->join($join, $condition, 'FULL', $bind);
    }

    /**
     * Note: 获取JOIN表名以及别名
     * Date: 2023-04-27
     * Time: 14:30
     * @param string|array|Raw $join
     * @param string $alias 别名
     * @return string|array
     */
    protected function getJoinTable($join, &$alias = null)
    {
        if (is_array($join)) {
            $table = $join;
            array_shift($join);
            $alias = $join;
            return $table;
        } elseif ($join instanceof Raw) {
            return $join;
        }

        $join = trim($join);

        if (strpos($join, '(') !== false) {
            $table = $join;
        } else {
            if (strpos($join, ' ')) {
                [$table, $alias] = explode(' ', $join);
            } elseif (strpos($join, '.')) {
                [$talbe, $alias] = explode('.', $join);
            } else {
                $table = $join;
                $alias = $join;
            }

            if ($this->prefix && strpos($table, '.') === false && strpos($table, $this->prefix) !== 0) {
                $table = $this->getTable($table);
            }
        }

        if (!empty($alias) && $table != $alias) {
            $table = [$table => $alias];
        }

        return $table;
    }

    /**
     * Note: 指定JOIN查询字段
     * Date: 2023-03-29
     * Time: 14:50
     * @param string|array $join 数据表
     * @param string|array $field 字段
     * @param string $on JOIN条件
     * @param string $type JOIN类型
     * @param array $bind 参数绑定
     * @return $this
     */
    public function view($join, $field = true, $on = null, string $type = 'INNER', array $bind = [])
    {
        $this->options['view'] = true;

        $fields = [];
        $table = $this->getJoinTable($join, $alias);

        if ($field === true) {
            $fields = $alias . '.*';
        } else {
            if (is_string($field)) {
                $field = explode(',', $field);
            }

            foreach ($field as $key => $value) {
                if (is_numeric($key)) {
                    $fields[] = $alias . '.' . $value;

                    $this->options['map'][$value] = $alias . '.' . $value;
                } else {
                    if (preg_match('/[,=\.\'\"\(\s]/', $key)) {
                        $name = $key;
                    } else {
                        $name = $alias . '.' . $key;
                    }

                    $fields[] = $name . ' AS ' . $value;

                    $this->options['map'][$value] = $name;
                }
            }
        }

        $this->field($field);

        if ($on) {
            $this->join($table, $on, $type, $bind);
        } else {
            $this->table($table);
        }

        return $this;
    }

    /**
     * Note: 视图查询处理
     * Date: 2023-03-29
     * Time: 18:11
     * @param array $options 查询 参数
     * @return void
     */
    public function parseView(array &$options)
    {
        foreach (['AND', 'OR'] as $logic) {
            if (isset($options['where'][$logic])) {
                foreach ($options['where'][$logic] as $key => $value) {
                    if (array_key_exists($value[0], $options['map'])) {
                        array_shift($value);
                        array_unshift($value, $options['map'][$value[0]]);
                        $options['where'][$logic][] = $value;
                        unset($options['where'][$logic][$key]);
                    }
                }
            }
        }

        if (isset($options['order'])) {
            foreach ($options['order'] as $key => $value) {
                if (is_numeric($key) && is_string($value)) {
                    if (strpos($value, ' ')) {
                        [$field, $sort] = explode(' ', $value);
                        if (array_key_exists($field, $options['map'])) {
                            $options['order'][$options['map'][$field]] = $sort;
                            unset($options['order'][$key]);
                        }
                    } elseif (array_key_exists($value, $options['map'])) {
                        $options['order'][$options['map'][$field]] = 'asc';
                        unset($options['order'][$key]);
                    }
                } elseif (array_key_exists($key, $options['map'])) {
                    $options['order'][$options['map'][$key]] = $value;
                    unset($options['order'][$key]);
                }
            }
        }
    }
}