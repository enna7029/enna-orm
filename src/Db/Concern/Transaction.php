<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Db\Exception\PDOException;

/**
 * 事务支持
 * Trait Transaction
 * @package Enna\Orm\Db\Concern
 */
trait Transaction
{
    /**
     * Note: 执行数据局XA事务
     * Date: 2023-04-07
     * Time: 16:30
     * @param $callback
     * @param array $dbs
     * @return mixed
     * @throws PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function transactionXa($callback, array $dbs = [])
    {
        $xid = uniqid('xa');

        if (empty($dbs)) {
            $dbs[] = $this->getConnection();
        }

        foreach ($dbs as $db) {
            $db->startTransXa($xid);
        }

        try {
            $result = null;
            if (is_callable($callback)) {
                $result = call_user_func_array($callback, [$this]);
            }

            foreach ($dbs as $db) {
                $db->prepareXa($xid);
            }

            foreach ($dbs as $db) {
                $db->commitXa($xid);
            }

            return $result;
        } catch (\Exception | \Throwable $e) {
            foreach ($dbs as $db) {
                $db->rollbackXa($xid);
            }
            throw $e;
        }
    }

    /**
     * Note: 执行数据库事务
     * Date: 2023-04-07
     * Time: 16:47
     * @param callback $callback
     * @return mixed
     */
    public function transaction(callback $callback)
    {
        return $this->getConnection()->transaction($callback);
    }

    /**
     * Note: 启动事务
     * Date: 2023-04-07
     * Time: 16:50
     * @return void
     */
    public function startTrans()
    {
        return $this->getConnection()->startTrans();
    }

    /**
     * Note: 提交事务
     * Date: 2023-04-07
     * Time: 16:50
     * @return void
     */
    public function commit()
    {
        return $this->getConnection()->commit();
    }

    /**
     * Note: 事务回滚
     * Date: 2023-04-07
     * Time: 16:50
     * @return void
     */
    public function rollback()
    {
        return $this->getConnection()->rollback();
    }
}