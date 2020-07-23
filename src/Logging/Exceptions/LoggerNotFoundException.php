<?php

declare(strict_types=1);

namespace NB\Components\Logging\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class LoggerNotFoundException extends \Exception implements NotFoundExceptionInterface
{

}