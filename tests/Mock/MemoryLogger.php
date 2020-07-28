<?php

declare(strict_types=1);

namespace NB\AppComponents\Test\Mock;

use Psr\Log\LoggerInterface;

class MemoryLogger implements LoggerInterface
{
    private string $latestMessage = '';

    private array $latestContext = [];

    public function getLatestMessage(): string
    {
        return $this->latestMessage;
    }

    public function getLatestContext(): array
    {
        return $this->latestContext;
    }

    public function emergency($message, array $context = [])
    {
        $this->recordLog($message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->recordLog($message, $context);
    }

    private function recordLog($message, array $context = [])
    {
        $this->latestMessage = $message;
        $this->latestContext = $context;
    }
}