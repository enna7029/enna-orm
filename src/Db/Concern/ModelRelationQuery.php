<?php
declare(strict_types=1);

namespace Enna\Orm\Db\Concern;

use Enna\Orm\Model;
use Closure;

/**
 * 模型及关联查询
 * Trait ModelRelationQuery
 * @package Enna\Orm\Db\Concern
 */
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

    /**
     * Note: 添加查询范围
     * Date: 2023-05-12
     * Time: 18:25
     * @param array|string|Closure $scope 查询范围定义
     * @param array ...$args
     * @return $this
     */
    public function scope($scope, ...$args)
    {
        array_unshift($args, $this);

        if ($scope instanceof Closure) {
            call_user_func_array($scope, $args);
            return $this;
        }

        if (is_string($scope)) {
            $scope = explode(',', $scope);
        }

        if ($this->model) {
            foreach ($scope as $name) {
                $method = 'scope' . trim($name);

                if (method_exists($this, $method)) {
                    call_user_func_array([$this, $method], $args);
                }
            }
        }

        return $this;
    }
}