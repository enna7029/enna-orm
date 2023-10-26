<?php
declare(strict_types=1);

namespace Enna\Orm;

class Exception extends \Exception
{
    /**
     * 保存异常页面显示的额外Debug数据
     * @var array
     */
    protected $data = [];

    /**
     * Note: 设置异常额外的Debug数据
     * Date: 2023-10-11
     * Time: 15:22
     * @param string $label 数据分类，用于异常页面显示
     * @param array $data 需要显示的数据，必须为关联数组
     */
    final protected function setData(string $label, array $data)
    {
        $this->data[$label] = $data;
    }

    /**
     * Note: 额外的Debug数据
     * Date: 2023-10-11
     * Time: 15:23
     * @return array 由setData设置的Debug数据
     */
    final protected function getData()
    {
        return $this->data;
    }
}