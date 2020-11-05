<?php

declare(strict_types=1);

namespace NB\AppComponents\Test\HTTP\Client;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use NB\AppComponents\HTTP\Client\Logger;
use NB\AppComponents\Test\Mock\MemoryLogger;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\stream_for;

class LoggerTest extends TestCase
{
    public function testLog()
    {
        $mLogger = new MemoryLogger();

        $uri = new Uri("https://abc.com/test?abc=def&a%20b%20c=aaa");
        $request = new Request('POST', $uri);
        (new Logger($mLogger, [
            'logExtra' => [
                'abc' => 'def',
            ],
        ]))->log($request, null);
        $expect = [
            'request' => [
                'method' => 'POST',
                'scheme' => 'https',
                'host' => 'abc.com',
                'path' => '/test',
                'query' => [
                    'abc' => 'def',
                    'a b c' => 'aaa',
                ],
            ],
            'abc' => 'def',
        ];
        $this->assertEquals($expect, $mLogger->getLatestContext());

        ///////////////////////////////////////////////////////

        $uri = new Uri("https://abc.com/test");
        $request = new Request('POST', $uri);
        (new Logger($mLogger, [
            'logRequestHeaders' => false,
            'logExtra' => [
                'abc' => '123',
            ],
        ]))->log($request, null, ['abc' => 'def']);
        unset($expect['request']['headers'], $expect['request']['query']);
        $this->assertEquals($expect, $mLogger->getLatestContext());

        ///////////////////////////////////////////////////////

        $uri = new Uri("https://abc.com/test?abc=def&a%20b%20c=aaa");
        $request = new Request('POST', $uri, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], stream_for('abc=def&xyz=123'));
        $response = new Response(200, [
            'Content-Type' => 'application/json',
            'X-Request-Received-Time' => '1500',
            'X-Response-Time' => '1700',
        ], stream_for('{"abc":"123"}'));
        (new Logger($mLogger, [
            'logRequestHeaders' => true,
            'logResponseHeaders' => true,
            'requestRecvTimeHeader' => 'X-Request-Received-Time',
            'responseSentTimeHeader' => 'X-Response-Time',
            'logExceptionTrace' => false,
            'logRequestBodyTypes' => [
                'application/x-www-form-urlencoded',
            ],
            'logResponseBodyTypes' => [
                'application/json',
            ],
            'logExtra' => [
                'x1' => 'y1',
                'x2' => 'y2',
            ],
        ]))->log($request, $response, [
            'timeBeforeRequest' => 1.1,
            'timeAfterRespond' => 2.3,
            'exception' => new \Exception('error'),
            'x2' => 'y3',
        ]);
        $expect = [
            'request' => [
                'method' => 'POST',
                'scheme' => 'https',
                'host' => 'abc.com',
                'path' => '/test',
                'query' => [
                    'abc' => 'def',
                    'a b c' => 'aaa',
                ],
                'headers' => [
                    'Host' => 'abc.com',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'postForm' => [
                    'abc' => 'def',
                    'xyz' => '123',
                ],
            ],
            'response' => [
                'status' => 200,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Request-Received-Time' => '1500',
                    'X-Response-Time' => '1700',
                ],
                'body' => '{"abc":"123"}',
            ],
            'duration' => 1.2,
            'exception' => [
                'msg' => 'error',
            ],
            'x1' => 'y1',
            'x2' => 'y3',
        ];
        $this->assertEquals($expect, $mLogger->getLatestContext());

        ///////////////////////////////////////////////////////

        $uri = new Uri("https://abc.com/test");
        $request = new Request('POST', $uri);
        (new Logger($mLogger, [
            'logRequestHeaders' => true,]
        ))->log($request, null, [
            'exception' => new RequestException("timed out", $request, null, null, [
                'errno' => 28,
                'error' => 'xxx',
            ]),
        ]);
        $expect = [
            'request' => [
                'method' => 'POST',
                'scheme' => 'https',
                'host' => 'abc.com',
                'path' => '/test',
                'headers' => [
                    'Host' => 'abc.com',
                ],
            ],
            'curl' => [
                'errno' => 28,
                'error' => 'xxx',
            ],
        ];
        $this->assertEquals($expect, $mLogger->getLatestContext());
    }
}