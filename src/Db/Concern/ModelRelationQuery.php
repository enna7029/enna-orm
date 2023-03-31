<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Model;

trait ModelRelationQuery
{
    /**
     * 当前模型对象
     * @var Model
     */
    protected $model;

    /**
     * Note: 指定模型
     * Date: 2023-03-31
     * Time: 14:36
     * @param Model $model 模型对象实例
     * @return $this
     */
    public function model(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Note: 获取当前模型对象实例
     * Date: 2023-03-31
     * Time: 14:36
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }
}