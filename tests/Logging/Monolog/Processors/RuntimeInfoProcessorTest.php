<?php

declare(strict_types=1);

namespace NB\AppComponents\Test\Logging\Monolog\Processors;

use NB\AppComponents\Logging\Monolog\Processors\RuntimeInfoProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class RuntimeInfoProcessorTest extends TestCase
{
    public function testRuntimeInfoProcessor()
    {
        $stream = fopen("php://memory", "rw");
        $logger = $this->createLogger($stream);
        $logger->info("test", ['abc' => 'def']);
        fseek($stream, 0);
        $this->assertEquals([
            'runtime' => [
                'lang' => 'PHP-'.phpversion(),
                'sapi' => 'cli',
                'pid' => getmypid(),
            ],
        ], json_decode(fgets($stream), true)['extra']);
        fclose($stream);
    }

    private function createLogger($stream): Logger
    {
        $l = new Logger('test');
        $l->pushProcessor(new RuntimeInfoProcessor([
            'withMemoryUsage' => false,
        ]));
        $handler = new StreamHandler($stream);
        $handler->setFormatter(new JsonFormatter());
        $l->setHandlers([$handler]);

        return $l;
    }
}