<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Closure;
use Enna\Orm\Db\BaseQuery;
use Enna\Orm\Db\Raw;
use Predis\Command\Redis\MIGRATE;
use Predis\Command\Redis\WATCH;

/**
 * where条件查询
 * Trait WhereQuery
 * @package Enna\Orm\Db\Concern
 */
trait WhereQuery
{
    /**
     * Note: 指定AND查询条件
     * Date: 2023-04-08
     * Time: 9:41
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        if ($field instanceof $this) {
            $this->parseQueryWhere($field);
            return $this;
        } elseif ($field === true || $field === 1) {
            $this->options['where']['and'][] = true;
            return $this;
        }

        $param = func_get_args();
        array_shift($param);

        return $this->parseWhereExp('AND', $field, $op, $condition, $param);
    }

    /**
     * Note: 解析Query对象查询条件
     * Date: 2023-05-23
     * Time: 10:53
     * @param BaseQuery $query 查询对象
     * @return void
     */
    protected function parseQueryWhere(BaseQuery $query)
    {
        $this->options['where'] = $query->getOptions('where');
        
    }

    /**
     * Note: 指定AND查询条件
     * Date: 2023-04-08
     * Time: 14:23
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);

        return $this->parseWhereExp('OR', $field, $op, $condition, $param);
    }

    /**
     * Note: 指定XOR查询条件
     * Date: 2023-04-08
     * Time: 14:38
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereXor($field, $op = null, $condition = null)
    {
        $param = func_get_args();
        array_shift($param);

        return $this->parseWhereExp('XOR', $field, $op, $condition, $param);
    }

    /**
     * Note: 指定NULL查询条件
     * Date: 2023-04-08
     * Time: 15:19
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NULL', null, [], true);
    }

    /**
     * Note: 指定NOT NULL查询条件
     * Date: 2023-04-08
     * Time: 15:22
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNotNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOTNULL', null, [], true);
    }

    /**
     * Note: 指定EXISTS查询条件
     * Date: 2023-04-08
     * Time: 15:40
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereExists($condition, string $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = new Raw($condition);
        }

        $this->options['where'][strtoupper($logic)][] = ['', 'EXISTS', $condition];

        return $this;
    }

    /**
     * Note: 指定NOT EXISTS查询条件
     * Date: 2023-04-08
     * Time: 15:40
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNotExists($condition, string $logic = 'AND')
    {
        if (is_string($condition)) {
            $condition = new Raw($condition);
        }

        $this->options['where'][strtoupper($logic)][] = ['', 'NOT EXISTS', $condition];

        return $this;
    }

    /**
     * Note: 指定In查询条件
     * Date: 2023-04-08
     * Time: 15:42
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereIn(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'IN', $condition, [], true);
    }

    /**
     * Note: 指定Not In查询条件
     * Date: 2023-04-08
     * Time: 15:42
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNotIn(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT IN', $condition, [], true);
    }

    /**
     * Note: 指定LIKE查询条件
     * Date: 2023-04-08
     * Time: 15:46
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereLike(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'LIKE', $condition, [], true);
    }

    /**
     * Note: 指定NOT LIKE查询条件
     * Date: 2023-04-08
     * Time: 15:46
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNotLike(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT LIKE', $condition, [], true);
    }

    /**
     * Note: 指定BETWEEN查询条件
     * Date: 2023-04-08
     * Time: 15:48
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereBetween(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'BETWEEN', $condition, [], true);
    }

    /**
     * Note: 指定NOT BETWEEN查询条件
     * Date: 2023-04-08
     * Time: 15:48
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereNotBetween(string $field, $condition, $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT BETWEEN', $condition, [], true);
    }

    /**
     * Note: 指定EX查询条件
     * Date: 2023-04-08
     * Time: 11:00
     * @param string $field 查询字段
     * @param string $where 查询条件
     * @param array $bind 参数绑定
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereExp(string $field, string $where, array $bind = [], string $logic = 'AND')
    {
        $this->options['where'][$logic][] = [$field, 'EXP', new Raw($where, $bind)];

        return $this;
    }

    /**
     * Note: 指定表达式查询
     * Date: 2023-04-08
     * Time: 14:48
     * @param string $where 查询条件
     * @param array $bind 参数绑定
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereRaw(string $where, array $bind = [], string $logic = 'AND')
    {
        $this->options['where'][$logic][] = new Raw($where, $bind);

        return $this;
    }

    /**
     * Note:
     * User: enna
     * Date: 2023-04-08
     * Time: 17:05
     * @param string $where 查询条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function whereOrRaw(string $where, array $bind = [])
    {
        return $this->whereRaw($where, $bind, 'OR');
    }

    /**
     * Note: 指定字段RAW查询
     * Date: 2023-04-08
     * Time: 17:05
     * @param string $field 查询字段
     * @param mixed $op 查询表达式
     * @param string $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereFieldRaw(string $field, $op, $condition = null, string $logic = 'AND')
    {
        if (is_null($condition)) {
            $condition = $op;
            $op = '=';
        }

        $this->options['where'][$logic][] = [new Raw($field), $op, $condition];
        return $this;
    }

    /**
     * Note: 比较两个字段
     * Date: 2023-04-08
     * Time: 17:52
     * @param string $field1 查询字段
     * @param string $op 比较操作服
     * @param string|null $field2 查询字段
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereColumn(string $field1, string $op, string $field2 = null, string $logic = 'AND')
    {
        if (is_null($field2)) {
            $field2 = $op;
            $op = '=';
        }

        return $this->parseWhereExp($logic, $field1, 'COLUMN', [$op, $field2], [], true);
    }

    /**
     * Note: 指定FNND_IN_SET查询
     * Date: 2023-04-08
     * Time: 17:56
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function whereFindInSet(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field1, 'FIND IN SET', $condition, [], true);
    }

    /**
     * Note: 设置软删除字段以及条件
     * Date: 2023-04-08
     * Time: 18:00
     * @param string $field 查询字段
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function useSoftDelete(string $field, $condition = null)
    {
        if ($field) {
            $this->options['soft_delete'] = [$field, $condition];
        }

        return $this;
    }

    /**
     * Note: 去除某个字段查询条件
     * Date: 2023-04-08
     * Time: 17:03
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function removeWhereField(string $field, string $logic = 'AND')
    {
        $logic = strtoupper($logic);

        if (isset($this->options['where'][$logic])) {
            foreach ($this->options['wehre'][$logic] as $key => $val) {
                if (is_array($val) && $val[0] == $field) {
                    unset($this->options['where'][$logic][$key]);
                }
            }
        }
    }

    /**
     * Note: 条件查询
     * Date: 2023-04-08
     * Time: 10:08
     * @param mixed $condition 满足条件(支持闭包)
     * @param Closure|array $query 满足条件后,执行的查询
     * @param Closure|array $otherwise 不满足条件后,执行的查询
     * @return $this
     */
    public function when($condition, $query, $otherwise = null)
    {
        if ($condition instanceof Closure) {
            $condition = $condition($this);
        }

        if ($condition) {
            if ($query instanceof Closure) {
                $query($this, $condition);
            } elseif (is_array($query)) {
                $this->where($query);
            }
        } elseif ($otherwise) {
            if ($otherwise instanceof Closure) {
                $otherwise($this, $condition);
            } elseif (is_array($otherwise)) {
                $this->where($otherwise);
            }
        }

        return $this;
    }

    /**
     * Note: 分析查询表达式
     * Date: 2023-04-08
     * Time: 10:29
     * @param string $logic 查询逻辑 and|or
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @param array $param 查询参数
     * @param bool $strict 严格模式
     * @return $this
     */
    protected function parseWhereExp(string $logic, $field, $op, $condition, array $param = [], bool $strict = false)
    {
        $logic = strtoupper($logic);

        if (is_string($field) && !empty($this->options['via']) && strpos($field, '.') === false) {
            $field = $this->options['via'] . '.' . $field;
        }

        if ($field instanceof Raw) {
            return $this->whereRaw($field, is_array($op) ? $op : [], $logic);
        } elseif ($strict) {
            if ($op == '=') {
                $where = $this->whereEq($field, $condition);
            } else {
                $where = [$field, $op, $condition, $logic];
            }
        } elseif (is_array($field)) {
            return $this->parseArrayWhereItems($field, $logic);
        } elseif ($field instanceof Closure) {
            $where = $field;
        } elseif (is_string($field)) {
            if (preg_match('/[,=\<\'\"\(\s]/', $field)) {
                return $this->whereRaw($field, is_array($op) ? $op : [], $logic);
            } elseif (is_string($op) && strtolower($op) == 'exp' && !is_null($condition)) {
                $bind = isset($param[2]) && is_array($param[2]) ? $param[2] : [];
                return $this->whereExp($field, $condition, $bind, $logic);
            }

            $where = $this->parseWhereItem($logic, $field, $op, $condition, $param);
        }

        if (!empty($where)) {
            $this->options['where'][$logic][] = $where;
        }

        return $this;
    }

    /**
     * Note: 分析查询表达式
     * Date: 2023-04-08
     * Time: 11:04
     * @param string $logic 查询逻辑 and|or
     * @param mixed $field 查询字段
     * @param mixed $op 查询表达式
     * @param mixed $condition 查询条件
     * @param array $param 查询参数
     * @return $this
     */
    protected function parseWhereItem(string $logic, $field, $op, $condition, array $param = [])
    {
        if ($field && is_null($condition)) {
            if (is_string($op) && in_array(strtoupper($op), ['NULL', 'NOT NULL', 'NOTNULL'], true)) {
                $where = [$field, $op, ''];
            } elseif ($op === '=' || is_null($op)) {
                $where = [$field, 'NULL', ''];
            } elseif ($op === '<>') {
                $where = [$field, 'NOTNULL', ''];
            } else {
                $where = $this->whereEq($field, $op);
            }
        } elseif (is_string($op) && in_array(strtoupper($op), ['EXISTS', 'NOT EXISTS', 'NOTEXISTS'], true)) {
            $where = [$field, $op, is_string($condition) ? new Raw($condition) : $condition];
        } else {
            $where = $field ? [$field, $op, $condition, $param[2] ?? null] : [];
        }

        return $where;
    }

    /**
     * Note: 相等查询的主键处理
     * Date: 2023-04-08
     * Time: 14:20
     * @param strin $field 字段名
     * @param mixed $value 字段值
     * @return array
     */
    protected function whereEq(strin $field, $value)
    {
        if ($this->getPk() == $field) {
            $this->options['key'] = $value;
        }

        return [$field, '=', $value];
    }

    /**
     * Note: 数组批量查询
     * Date: 2023-04-08
     * Time: 14:58
     * @param array $field 批量查询
     * @param string $logic 查询逻辑 and|or
     * @return $this
     */
    public function parseArrayWhereItems(array $field, string $logic)
    {
        if (key($field) !== 0) {
            $where = [];
            foreach ($field as $key => $val) {
                if ($val instanceof Raw) {
                    $where[] = [$key, 'exp', $val];
                } else {
                    $where[] = is_null($val) ? [$key, 'NULL', ''] : [$key, is_array($val) ? 'IN' : '=', $val];
                }
            }
        } else {
            $where = $field;
        }

        if (!empty($where)) {
            $this->options['where'][$logic] = isset($this->options['where']['logic']) ? array_merge($this->options['where']['logic'], $where) : $where;
        }

        return $this;
    }
}