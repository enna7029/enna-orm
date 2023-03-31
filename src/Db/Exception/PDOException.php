<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Exception;

class PDOException extends DbException
{
    public function __construct(\PDOException $exception, array $config = [], string $sql = '', int $code = 10501)
    {
        $error = $exception->errorInfo;
        $message = $exception->getMessage();

        if (!empty($error)) {
            $this->setData('PDO Error Info', [
                'SQLSTATE' => $error[0],
                'Driver Error Code' => isset($error[1]) ? $error[1] : 0,
                'Driver Error Message' => isset($error[2]) ? $error[2] : '',
            ]);
        }

        parent::__construct($message, $config, $sql, $code);
    }
}