<?php
declare(strict_types=1);

namespace Enna\Orm\Contract;

interface Jsonable
{
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string;
}