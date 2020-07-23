<?php

declare(strict_types=1);

namespace NB\Components\Logging\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class LoggerContainerException extends \Exception implements ContainerExceptionInterface
{

}