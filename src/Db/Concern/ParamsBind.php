<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

trait ParamsBind
{
    /**
     * 当前参数绑定
     * @var array
     */
    protected $bind = [];

    /**
     * Note: 批量参数绑定
     * Date: 2023-03-29
     * Time: 17:23
     * @param array $value 绑定变量值
     * @return $this
     */
    public function bind(array $value)
    {
        $this->bind = array_merge($this->bind, $value);

        return $this;
    }

    /**
     * Note: 获取绑定的参数
     * Date: 2023-03-29
     * Time: 17:24
     * @param bool $clear 是否清空绑定数据
     * @return array
     */
    public function getBind(bool $clear = true)
    {
        $bind = $this->bind;
        if ($clear) {
            $this->bind = [];
        }

        return $bind;
    }

    /**
     * Note: 参数绑定
     * Date: 2023-03-30
     * Time: 15:59
     * @param string $sql SQL表达式
     * @param array $bind 参数绑定
     * @return void
     */
    public function bindParams(string &$sql, array $bind = [])
    {
        foreach ($bind as $key => $value) {
            if (is_array($value)) {
                $name = $this->bindValue($value[0], $value[1], $value[2] ?? null);
            } else {
                $name = $this->bindValue($value);
            }

            if (is_numeric($key)) {
                $sql = substr_replace($sql, ':' . $name, strpos($sql, '?'), 1);
            } else {
                $sql = str_replace(':' . $key, ':' . $name, $sql);
            }
        }
    }

    /**
     * Note: 单个参数绑定
     * Date: 2023-03-30
     * Time: 16:28
     * @param mixed $value 绑定变量值
     * @param int|null $type 绑定类型
     * @param string|null $name 绑定标识
     * @return string
     */
    public function bindValue($value, int $type = null, string $name = null)
    {
        $name = $name ?: 'Bind' . (count($this->bind) + 1) . '_' . mt_rand() . '_';

        $this->bind[$name] = [$value, $type ?: PDO::PARAM_STR];

        return $name;
    }

}