<?php
declare(strict_types1=1);

namespace Enna\Orm\Model\Concern;

use Enna\Orm\Db\Query;

trait SoftDelete
{
    /**
     * 包含软删除数据
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Note: 查询时,默认排除软删除数据
     * Date: 2023-05-12
     * Time: 16:20
     * @param Query $query
     * @return void
     */
    public function withNoTrashed(Query $query)
    {
        $field = $this->getDeleteTimeField(true);
        if ($field) {
            $condition = is_null($field) ? ['null', ''] : ['=', $this->defaultSoftDelete];
            $query->useSoftDelete($field, $condition);
        }
    }

    /**
     * Note: 获取软删除字段
     * Date: 2023-05-12
     * Time: 16:25
     * @param bool $read 是否查询操作
     * @return string|false
     */
    protected function getDeleteTimeField(bool $read = false)
    {
        $field = property_exists($this, 'delete_time') && isset($this->deleteTime) ? $this->deleteTime : 'delete_time';

        if ($field == false) {
            return false;
        }

        if (strpos($field, '.') === false) {
            $field = '__TABLE__' . $field;
        }

        if (!$read && strpos('.', $field)) {
            $array = explode('.', $field);
            $field = array_pop($array);
        }

        return $field;
    }
}