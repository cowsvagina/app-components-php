<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class LoggerContainerException extends \Exception implements ContainerExceptionInterface
{

}