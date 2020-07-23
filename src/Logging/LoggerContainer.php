<?php

declare(strict_types=1);

namespace NB\Components\Logging;

use NB\Components\Logging\Exceptions\LoggerContainerException;
use NB\Components\Logging\Exceptions\LoggerNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class LoggerContainer implements ContainerInterface
{
    protected array $registry = [];

    /**
     * @inheritDoc
     */
    public function get($id): LoggerInterface
    {
        if (!is_string($id)) {
            throw new LoggerContainerException('id should be string type.');
        }

        if (!$this->has($id)) {
            throw new LoggerNotFoundException("unregistered logger of {$id}");
        }

        return $this->registry[$id];
    }

    /**
     * @inheritDoc
     */
    public function has($id): bool
    {
        if (!is_string($id)) {
            return false;
        }

        return isset($this->registry[$id]);
    }

    public function register(string $id, LoggerInterface $logger)
    {
        $this->registry[$id] = $logger;
    }

    public function unregister(string $id)
    {
        unset($this->registry[$id]);
    }
}
