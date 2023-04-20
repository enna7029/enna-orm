<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Exception;

class DataNotFoundException extends DbException
{
    protected $table;

    public function __construct(string $message, string $table, array $config = [])
    {
        $this->message = $message;
        $this->table = $table;

        $this->setData('Database Config', $config);
    }

    /**
     * Note: 获取数据表名
     * Date: 2023-04-20
     * Time: 10:12
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }
}