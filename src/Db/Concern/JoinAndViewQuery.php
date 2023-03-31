<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

trait JoinAndViewQuery
{
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

    }
}