<?php

declare(strict_types=1);

namespace NB\AppComponents\Logging\Monolog\Formatters;

trait HelperTrait
{
    private function getType($var)
    {
        return is_object($var) ? get_class($var) : gettype($var);
    }
}