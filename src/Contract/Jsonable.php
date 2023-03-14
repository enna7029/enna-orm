<?php
declare(strice_types=1);

namespace Enna\Orm\Contract;

interface Jsonable
{
    public function toJson($options = JSON_UNESCAPED_UNICODE): string;
}