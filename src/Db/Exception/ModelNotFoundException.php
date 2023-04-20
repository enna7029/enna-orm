<?php
declare(strict_types=1);

use Enna\Orm\Db\Exception\DbException;

class ModelNotFoundException extends DbException
{
    protected $model;

    public function __construct(string $message, string $model = '', array $config = [])
    {
        $this->message = $message;
        $this->model = $model;

        $this->setData('Database Config', $config);
    }

    /**
     * Note: 获取模型类名
     * Date: 2023-04-20
     * Time: 10:29
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }
}