<?php
declare(strict_types=1);

namespace Enna\Orm\Facade;

use Enna\Framework\Facade;

class Db extends Facade
{
    protected static function getFacadeClass()
    {
        return 'Enna\Orn\DbManager';
    }
}