<?php
declare(strict_types=1);

namespace Enna\Orm\Contract;

interface Arrayable
{
    public function toArray(): array;
}