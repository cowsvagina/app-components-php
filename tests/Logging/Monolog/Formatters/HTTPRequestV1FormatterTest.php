<?php

declare(strict_types=1);

namespace NB\AppComponents\Test\Logging\Monolog\Formatters;

use PHPUnit\Framework\TestCase;
use NB\AppComponents\Logging\Monolog\Formatters\HTTPRequestV1Formatter;
use GuzzleHttp\Psr7\ServerRequest;

class HTTPRequestV1FormatterTest extends TestCase
{
    private string $service = 'test.service';
    private string $env = 'prod';

    public function testValidInfo()
    {
        $formatter = new HTTPRequestV1Formatter($this->service, $this->env);

        $now = new \DateTime();
        $req = (new ServerRequest('POST', '/test?q1=1&q2=2', [
            'content-type' => 'application/x-www-form-urlencoded',
        ]))->withQueryParams(['q1' => '1', 'q2' => '2'])->withParsedBody(['q3' => '3', 'q4' => '4']);
        $record = [
            'message' => 'test',
            'context' => [
                HTTPRequestV1Formatter::KEY_REQUEST => $req,
                HTTPRequestV1Formatter::KEY_USER => 'userID',
                HTTPRequestV1Formatter::KEY_IP => '1.1.1.1',
                HTTPRequestV1Formatter::KEY_RUNTIME => 100,
            ],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => $now,
            'extra' => [],
        ];
        $actual = $formatter->format($record);
        $expect = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->env,
            'time' => $now->format('Y-m-d\TH:i:s.uP'),
            'method' => 'POST',
            'path' => '/test',
            'headers' => ['content-type' => 'application/x-www-form-urlencoded'],
            'get' => ['q1' => '1', 'q2' => '2'],
            'post' => ['q3' => '3', 'q4' => '4'],
            'context' => [
                'ip' => '1.1.1.1',
                'user' => 'userID',
                'runtime' => 100,
            ],
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);


        // 测试额外字段
        $record['context']['haha'] = 'gaga';
        $actual = $formatter->format($record);
        $expect['context'] = [
            'haha' => 'gaga',
            'ip' => '1.1.1.1',
            'user' => 'userID',
            'runtime' => 100,
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);


        // 测试header黑白名单
        $formatter->setHeadersWhitelist(['content-type', 'white1', 'white2']);
        $formatter->setHeadersBlacklist(['white2']);    // white2 被归入黑名单,在输出日志的时候就不应该包含这个header
        $req = $req->withHeader('white1', 'v1')->withHeader('white2', 'v2')->withHeader('white3', 'v3');
        $record['context'][HTTPRequestV1Formatter::KEY_REQUEST] = $req;
        $actual = $formatter->format($record);
        $expect['headers'] = ['content-type' => 'application/x-www-form-urlencoded', 'white1' => 'v1'];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);
    }

    /**
     * 测试request为一个非法类型所生成的log
     * @covers \NB\AppComponents\Logging\Monolog\Formatters\HTTPRequestV1Formatter::format
     */
    public function testInvalidInfo_WithInvalidRequest()
    {
        $formatter = new HTTPRequestV1Formatter($this->service, $this->env);

        $now = new \DateTime();
        $record = [
            'message' => 'test',
            'context' => [],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => $now,
            'extra' => [],
        ];
        $actual = $formatter->format($record);
        $expect = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->env,
            'time' => $now->format('Y-m-d\TH:i:s.uP'),
            'method' => '',
            'path' => '',
            'headers' => new class{},
            'get' => new class{},
            'post' => new class{},
            'context' => [
                'ctxErr' => [
                    [
                        'msg' => 'request object is not a PSR-7 request',
                        'type' => 'NULL',
                    ],
                ],
            ],
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);

        $record['context'][HTTPRequestV1Formatter::KEY_REQUEST] = new HTTPRequestV1FormatterTest();
        $actual = $formatter->format($record);
        $expect['context']['ctxErr'][0]['type'] = "NB\\AppComponents\\Test\\Logging\\Monolog\\Formatters\\HTTPRequestV1FormatterTest";
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);
    }

    /**
     * 测试user为一个非法类型所生成的log
     */
    public function testInvalidInfo_WithInvalidUser()
    {
        $formatter = new HTTPRequestV1Formatter($this->service, $this->env);

        $now = new \DateTime();
        $record = [
            'message' => 'test',
            'context' => [
                HTTPRequestV1Formatter::KEY_REQUEST => new ServerRequest('GET', '/test'),
                HTTPRequestV1Formatter::KEY_USER => [],
                HTTPRequestV1Formatter::KEY_IP => '1.1.1.1',
            ],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => $now,
            'extra' => [],
        ];
        $actual = $formatter->format($record);
        $expect = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->env,
            'time' => $now->format('Y-m-d\TH:i:s.uP'),
            'method' => 'GET',
            'path' => '/test',
            'headers' => new class{},
            'get' => new class{},
            'post' => new class{},
            'context' => [
                'ip' => '1.1.1.1',
                'ctxErr' => [
                    [
                        'msg' => 'invalid type of user field',
                        'type' => 'array',
                    ],
                ],

            ],
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);

        $record['context'][HTTPRequestV1Formatter::KEY_USER] = new HTTPRequestV1FormatterTest();
        $actual = $formatter->format($record);
        $expect['context']['ctxErr'][0]['type'] = "NB\\AppComponents\\Test\\Logging\\Monolog\\Formatters\\HTTPRequestV1FormatterTest";
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);
    }

    /**
     * 测试ip为一个非法类型所生成的log
     */
    public function testInvalidInfo_WithInvalidIP()
    {
        $formatter = new HTTPRequestV1Formatter($this->service, $this->env);

        $now = new \DateTime();
        $record = [
            'message' => 'test',
            'context' => [
                HTTPRequestV1Formatter::KEY_REQUEST => new ServerRequest('GET', '/test'),
                HTTPRequestV1Formatter::KEY_USER => 1,
                HTTPRequestV1Formatter::KEY_IP => [],
                HTTPRequestV1Formatter::KEY_RUNTIME => [],
            ],
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => $now,
            'extra' => [],
        ];
        $actual = $formatter->format($record);
        $expect = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->env,
            'time' => $now->format('Y-m-d\TH:i:s.uP'),
            'method' => 'GET',
            'path' => '/test',
            'headers' => new class{},
            'get' => new class{},
            'post' => new class{},
            'context' => [
                'ctxErr' => [
                    [
                        'msg' => 'invalid type of ip field',
                        'type' => 'array',
                    ],
                    [
                        'msg' => 'invalid type of runtime field',
                        'type' => 'array',
                    ],
                ],
                'user' => '1',
            ],
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);

        $record['context'][HTTPRequestV1Formatter::KEY_IP] = new HTTPRequestV1FormatterTest();
        $actual = $formatter->format($record);
        $expect['context']['ctxErr'][0]['type'] = "NB\\AppComponents\\Test\\Logging\\Monolog\\Formatters\\HTTPRequestV1FormatterTest";
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);
    }

    /**
     * 测试context字段为一个非法类型所生成的log
     */
    public function testInvalidInfo_WithInvalidContext()
    {
        $formatter = new HTTPRequestV1Formatter($this->service, $this->env);

        $now = new \DateTime();
        $record = [
            'message' => 'test',
            'context' => 'invalid_context',   // 提供不合法的context类型
            'level' => 200,
            'level_name' => 'INFO',
            'channel' => 'test',
            'datetime' => $now,
            'extra' => [],
        ];
        $actual = $formatter->format($record);
        $expect = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->env,
            'time' => $now->format('Y-m-d\TH:i:s.uP'),
            'method' => '',
            'path' => '',
            'headers' => new class{},
            'get' => new class{},
            'post' => new class{},
            'context' => [
                'ctxErr' => [
                    [
                        'msg' => 'invalid context type in log data',
                        'type' => 'string',
                    ],
                    [
                        'msg' => 'request object is not a PSR-7 request',
                        'type' => 'NULL',
                    ],
                ],

            ],
        ];
        $this->assertEquals(json_encode($expect, JSON_UNESCAPED_SLASHES)."\n", $actual);
    }
}