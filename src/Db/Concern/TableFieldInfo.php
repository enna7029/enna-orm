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
}