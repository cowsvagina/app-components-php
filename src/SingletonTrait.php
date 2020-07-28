<?php

declare(strict_types=1);

namespace NB\Components;

trait SingletonTrait
{
    /** @var mixed */
    protected static $instance;

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }
}