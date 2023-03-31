<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Exception;

class BindParamException extends DbException
{
    public function __construct(string $message, array $config = [], string $sql = '', array $bind, int $code = 10502)
    {
        $this->setData('Bind Param', $bind);
        parent::__construct($message, $config, $sql, $code);
    }
}