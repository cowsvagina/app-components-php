<?php

declare(strict_types=1);

namespace NB\Components\Test\HTTP\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use NB\Components\Test\Mock\MemoryLogger;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use NB\Components\HTTP\Client\Logger;
use function GuzzleHttp\Psr7\stream_for;

class LoggerTest extends TestCase
{
    public function testLog()
    {
        $mLogger = new MemoryLogger();

        $uri = new Uri("https://abc.com/test?abc=def&a%20b%20c=aaa");
        $request = new Request('POST', $uri);
        (new Logger($mLogger))->log($request, null);
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
                ],
            ],
        ];
        $this->assertEquals($expect, $mLogger->getLatestContext());

        ///////////////////////////////////////////////////////

        $uri = new Uri("https://abc.com/test");
        $request = new Request('POST', $uri);
        (new Logger($mLogger, [
            'logRequestHeaders' => false
        ]))->log($request, null);
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
            'requestRecvTimeHeader' => 'X-Request-Received-Time',
            'responseSentTimeHeader' => 'X-Response-Time',
            'logExceptionTrace' => false,
        ]))->log($request, $response, [
            'timeBeforeRequest' => 1,
            'timeAfterRespond' => 2,
            'exception' => new \Exception('error'),
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
            'costs' => [
                'total' => 1,
                'upstream' => 0.5,
                'downstream' => 0.3,
            ],
            'exception' => [
                'msg' => 'error',
            ],
        ];
        $this->assertEquals($expect, $mLogger->getLatestContext());

        ///////////////////////////////////////////////////////

        $uri = new Uri("https://abc.com/test");
        $request = new Request('POST', $uri);
        (new Logger($mLogger, []))->log($request, null, [
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