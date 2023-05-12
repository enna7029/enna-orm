<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

trait TableFieldInfo
{
    /**
     * Note: 获取数据表字段信息
     * Date: 2023-04-10
     * Time: 17:32
     * @param string $tableName 数据表名
     * @return array
     */
    public function getTableFields($tableName = '')
    {
        if ($tableName == '') {
            $tableName = $this->getTable();
        }

        return $this->connection->getTableFields($tableName);
    }

    /**
     * Note: 获取字段类型信息
     * Date: 2023-04-28
     * Time: 14:41
     * @return array
     */
    public function getFieldsType()
    {
        if (!empty($this->options['field_type'])) {
            return $this->options['field_type'];
        }

        return $this->connection->getFieldsType($this->getTable());
    }

    /**
     * Note: 获取指定的字段类型信息
     * Date: 2023-05-05
     * Time: 16:00
     * @param string $field 字段名
     * @return string
     */
    public function getFieldType(string $field)
    {
        $fieldType = $this->getFieldsType();

        return $fieldType[$field] ?? null;
    }

    /**
     * Note: 获取字段类型信息
     * Date: 2023-04-28
     * Time: 14:23
     * @return array
     */
    public function getFieldsBindType()
    {
        $fieldType = $this->getFieldsType();

        return array_map([$this->connection, 'getFieldBindType'], $fieldType);
    }
}