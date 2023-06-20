<?php
declare(strict_types=1);

namespace Enna\Orm\Model;

use Enna\Orm\Model;

/**
 * 多对多中间模型表
 * Class Pivot
 * @package Enna\Orm\Model
 */
class Pivot extends Model
{
    /**
     * 父模型
     * @var Model
     */
    public $parent;

    /**
     * 是否时间自动写入
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * Pivot constructor.
     * @param array $data 数据
     * @param Model $parent 父模型
     * @param string $table 中间数据表名
     */
    public function __construct(array $data = [], Model $parent = null, string $table = '')
    {
        $this->parent = $parent;

        if (is_null($this->name)) {
            $this->name = $table;
        }

        parent::__construct($data);
    }
}