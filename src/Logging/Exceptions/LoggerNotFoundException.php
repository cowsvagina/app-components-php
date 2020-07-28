<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class LoggerNotFoundException extends \Exception implements NotFoundExceptionInterface
{

}