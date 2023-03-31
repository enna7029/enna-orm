<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Exception;

use Enna\Framework\Exception;
use Throwable;

class DbException extends Exception
{
    public function __construct(string $message, array $config = [], string $sql = '', int $code = 10500)
    {
        $this->message = $message;
        $this->code = $code;

        $this->setData('Database Status', [
            'Error Code' => $code,
            'Error Message' => $message,
            'Error Sql' => $sql,
        ]);
        unset($config['username'], $config['password']);
        $this->setData('Database Config', $config);
    }


}