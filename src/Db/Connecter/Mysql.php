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
}