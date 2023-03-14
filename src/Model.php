<?php
declare(strice_types=1);

namespace Enna\Orm;

use Closure;
use JsonSerializable;
use ArrayAccess;
use Enna\Orm\Contract\Arrayable;
use Enna\Orm\Contract\Jsonable;

abstract class Model implements JsonSerializable, ArrayAccess, Arrayable, Jsonable
{
    /**
     * 当前模型数据
     * @var array
     */
    private $data;

    public function __construct()
    {

    }
}