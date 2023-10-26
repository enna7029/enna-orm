<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

/**
 * 时间查询支持
 * Trait TimeFieldQuery
 * @package Enna\Orm\Db\Concern
 */
trait TimeFieldQuery
{

    protected $timeRule = [
        'today'      => ['tody', 'tomorrow -1second'],
        'yesterday'  => ['yesterday', 'today -1second'],
        'week'       => ['this week 00:00:00', 'next week 00:00:00 -1second'],
        'last week'  => ['last week 00:00:00', 'this week 00:00:00 -1second'],
        'month'      => ['first day of this month 00:00:00', 'first day of next month 00:00:00 -1second'],
        'last month' => ['first day of last month 00:00:00', 'first day of this month 00:00:00 -1second'],
        'year'       => ['this year 1/1', 'next year 1/1 -1second'],
        'last year'  => ['last year 1/1', 'this year 1/1 -1second'],
    ];

    /**
     * Note: 添加日期或者时间查询规则
     * Date: 2023-04-25
     * Time: 11:58
     * @param array $rule
     * @return $this
     */
    public function timeRule(array $rule)
    {
        $this->timeRule = array_merge($this->timeRule, $rule);

        return $this;
    }

    /**
     * Note: 查询日期或时间
     * Date: 2023-04-25
     * Time: 11:28
     * @param string $field 日期字段
     * @param string $op 比较运算符或表达式
     * @param string|array $range 比较范围
     * @param string $logic AND OR
     * @return $this
     */
    public function whereTime(string $field, string $op, $range = null, string $logic = 'AND')
    {
        if (is_null($range)) {
            if (isset($this->timeRule[$op])) {
                $range = $this->timeRule[$op];
            } else {
                $range = $op;
            }

            $op = is_array($range) ? 'between' : '>=';
        }

        return $this->parseWhereExp($logic, $field, strtolower($op) . ' time', $range, [], true);
    }

    /**
     * Note: 查询某个时间字段的间隔数据
     * Date: 2023-04-25
     * Time: 14:11
     * @param string $field 字段
     * @param string $start 开始时间
     * @param string $interval 时间间隔单位 second/minute/hour/day/week/mouth/year
     * @param int $step 间隔
     * @param string $logic 逻辑运算符 and/or
     * @return $this
     */
    public function whereTimeInterval(string $field, string $start, string $interval = 'day', int $step = 1, string $logic = 'AND')
    {
        $startTime = strtotime($start);
        $endTime = strtotime(($step > 0 ? '+' : '-') . abs($step) . ' ' . $interval . (abs($step) > 1 ? 's' : ''), $startTime);

        return $this->whereTime($field, 'between', $step > 0 ? [$startTime, $endTime - 1] : [$endTime, $startTime - 1], $logic);
    }

    /**
     * Note: 查询日数据
     * Date: 2023-04-25
     * Time: 14:30
     * @param string $field 字段
     * @param string $day 天
     * @param int $step 间隔
     * @param string $logic 运算逻辑服 and|or
     * @return $this
     */
    public function whereDay(string $field, string $day = 'this day', int $step = 1, string $logic = 'AND')
    {
        if (in_array($day, ['this day', 'last day'])) {
            $day = date('Y-m-d', strtotime($day));
        }

        return $this->whereTimeInterval($field, $day, 'day', $step, $logic);
    }

    /**
     * Note:查询周数据
     * Date: 2023-04-25
     * Time: 14:33
     * @param string $field 字段
     * @param string $week 周
     * @param int $step 间隔
     * @param string $logic 运算逻辑服 and|or
     * @return $this
     */
    public function whereWeek(string $field, string $week = 'this week', int $step = 1, string $logic = 'AND')
    {
        if (in_array($week, ['this week', 'last week'])) {
            $week = date('Y-m-d', strtotime($week));
        }

        return $this->whereTimeInterval($field, $week, 'week', $step, $logic);
    }

    /**
     * Note:查询月数据
     * Date: 2023-04-25
     * Time: 14:33
     * @param string $field 字段
     * @param string $week 月
     * @param int $step 间隔
     * @param string $logic 运算逻辑服 and|or
     * @return $this
     */
    public function whereMonth(string $field, string $month = 'this month', int $step = 1, string $logic = 'AND')
    {
        if (in_array($month, ['this month', 'last month'])) {
            $month = date('Y-m-d', strtotime($month));
        }

        return $this->whereTimeInterval($field, $month, 'month', $step, $logic);
    }

    /**
     * Note:查询年数据
     * Date: 2023-04-25
     * Time: 14:33
     * @param string $field 字段
     * @param string $week 年
     * @param int $step 间隔
     * @param string $logic 运算逻辑服 and|or
     * @return $this
     */
    public function whereYear(string $field, string $year = 'this year', int $step = 1, string $logic = 'AND')
    {
        if (in_array($year, ['this year', 'last year'])) {
            $year = date('Y-m-d', strtotime($year));
        }

        return $this->whereTimeInterval($field, $year, 'year', $step, $logic);
    }

    /**
     * Note: 查询某个时间区间
     * Date: 2023-04-25
     * Time: 14:46
     * @param string $field 字段
     * @param string|int $startTime 开始时间
     * @param string|int $endTime 结束时间
     * @param string $logic 逻辑运算符 and|or
     * @return $this
     */
    public function whereBetweenTime(string $field, $startTime, $endTime, string $logic = 'AND')
    {
        return $this->whereTime($field, 'between', [$startTime, $endTime], $logic);
    }

    /**
     * Note: 查询某个时间区间
     * Date: 2023-04-25
     * Time: 14:48
     * @param string $field 字段
     * @param string|int $startTime 开始时间
     * @param string|int $endTime 结束时间
     * @param string $logic 逻辑运算符 and|or
     * @return $this
     */
    public function whereNotBetweenTime(string $field, $startTime, $endTime, string $logic = 'AND')
    {
        return $this->whereTime($field, '<', $startTime, $logic)->whereTime($field, '>', $startTime, $logic);
    }

    /**
     * Note: 查询满足在指定2个时间字段范围内的数据
     * Date: 2023-04-25
     * Time: 14:53
     * @param string $startField 开始时间
     * @param string $endField 结束时间
     * @return $this
     */
    public function whereBetweenTimeField(string $startField, string $endField)
    {
        return $this->whereTime($startField, '<=', time())->whereTime($endField, '>=', time());
    }

    /**
     * Note: 查询不满足在指定2个时间字段范围内的数据
     * Date: 2023-04-25
     * Time: 14:55
     * @param string $startField 开始时间
     * @param string $endField 结束时间
     * @return $this
     */
    public function whereNotBetweenTimeField(string $startField, string $endField)
    {
        return $this->whereTime($startField, '>', time())->whereTime($endField, '<', time(), 'OR');
    }

}