<?php
declare(strict_types=1);

namespace Enna\Orm\Exception;

use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentInterface;

class InvalidArgumentException extends \InvalidArgumentException implements SimpleCacheInvalidArgumentInterface
{
}