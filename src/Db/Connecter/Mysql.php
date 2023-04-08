<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Connector;

use Enna\Orm\Db\PDOConnection;

class Mysql extends PDOConnection
{
    /**
     * Note: 解析PDO连接的dsn信息
     * Date: 2023-03-25
     * Time: 10:07
     * @param array $config 配置信息
     * @return string
     */
    public function parseDsn(array $config)
    {
        if (!empty($config['socket'])) {
            $dsn = 'mysql:unix_socket=' . $config['socket'];
        } elseif (!empty($config['hostport'])) {
            $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'];
        } else {
            $dsn = 'mysql:host=' . $config['hostname'];
        }

        $dsn .= ';dbname=' . $config['database'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        return $dsn;
    }

    /**
     * Note: 获取数据表字段信息
     * Date: 2023-03-28
     * Time: 15:23
     * @param string $tableName
     * @return array|void
     */
    public function getFields(string $tableName)
    {
        if (strpos($tableName, '`') === false) {
            if (strpos($tableName, '.')) {
                $tableName = str_replace('.', '`.`', $tableName);
            }
            $tableName = '`' . $tableName . '`';
        }

        $sql = 'SHOW FULL COLUMNS FROM ' . $tableName;
        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $info = [];
        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['field']] = [
                    'name' => $val['field'],
                    'type' => $val['type'],
                    'notnull' => $val['null'] == 'NO',
                    'default' => $val['default'],
                    'primary' => strtolower($val['key']) == 'pri',
                    'autoinc' => strtolower($val['extra']) == 'auto_increment',
                    'comment' => $val['comment'],
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * Note: 取得数据库的表信息
     * Date: 2023-04-06
     * Time: 14:55
     * @param string $dbName 数据表
     * @return array
     * @throws \Enna\Orm\Db\Exception\DbException
     */
    public function getTables(string $dbName = '')
    {
        $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES';
        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $info = [];
        foreach ($result as $key => $val) {
            $info[$key] = $val;
        }

        return $info;
    }

    /**
     * Note: 是否支持事务嵌套
     * Date: 2023-04-06
     * Time: 14:33
     * @return bool
     */
    protected function supportSavepoint()
    {
        return true;
    }

    /**
     * Note: 启动XA事务
     * Date: 2023-04-06
     * Time: 15:00
     * @param string $xid XA事务ID
     * @return void
     */
    public function startTransXa(string $xid)
    {
        $this->initConnect(true);
        $this->linkID->exec("XA START '$xid'");
    }

    /**
     * Note: 预编译XA事务
     * Date: 2023-04-07
     * Time: 9:57
     * @param string $xid XA事务ID
     * @return void
     */
    public function prepareXa(string $xid)
    {
        $this->initConnect(true);
        $this->linkID->exec("XA END '$xid'");
        $this->linkID->exec("XA PREPARE '$xid'");
    }

    /**
     * Note: 提交XA事务
     * Date: 2023-04-07
     * Time: 10:06
     * @param string $xid XA事务ID
     * @return void
     */
    public function commitXa(string $xid)
    {
        $this->initConnect(true);
        $this->linkID->exec("XA COMMIT '$xid'");
    }

    /**
     * Note: 回滚XA事务
     * Date: 2023-04-07
     * Time: 10:07
     * @param string $xid XA事务ID
     * @return void
     */
    public function rollbackXa(string $xid)
    {
        $this->initConnect(true);
        $this->linkID->exec("XA ROLLBACK '$xid'");
    }

}