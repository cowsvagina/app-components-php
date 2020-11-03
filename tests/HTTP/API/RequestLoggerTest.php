<?php

declare(strict_types=1);

namespace NB\Components\Test\HTTP\API;

use NB\AppComponents\HTTP\API\RequestLogger;
use NB\AppComponents\Logging\Monolog\Formatters\HTTPRequestV1Formatter;
use NB\AppComponents\Test\Mock\MemoryLogger;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class RequestLoggerTest extends TestCase
{
    public function testLog()
    {
        $logger = new MemoryLogger();

        $reqLogger = new RequestLogger($logger, [
            'logRealIP' => false,
            'errnoHeader' => '',
            'executionTimeHeader' => '',
            'response' => [
                'logHeaders' => false,
                'logBody' => false,
            ],
        ]);
        $request = new Request("GET", '/');
        $response = new Response(200, [
            'x-runtime' => 200,
            'x-errno' => 0,
        ]);
        $reqLogger->log($request, $response, ['userID' => 1]);
        $this->assertSame([
            HTTPRequestV1Formatter::KEY_REQUEST => $request,
            HTTPRequestV1Formatter::KEY_IP => '',
            HTTPRequestV1Formatter::KEY_USER_ID => '1',
            'response' => [
                'status' => 200,
            ],
        ], $logger->getLatestContext());

        /////////////////

        $reqLogger = new RequestLogger($logger, [
            'logRealIP' => true,
            'errnoHeader' => 'x-errno',
            'executionTimeHeader' => 'x-runtime',
            'requestSentTimeHeader' => 'cli-sent-time',
            'response' => [
                'logHeaders' => true,
                'logBody' => true,
            ],
        ]);
        $request = new Request("GET", '/', [
            'x-forwarded-for' => '2.2.2.2, 1.1.1.1',
            'cli-sent-time' => 100,
        ]);
        $response = new Response(200, [
            'content-type' => 'application/json',
        ], '{"data":"1"}');
        $reqLogger->log($request, $response, [
            'userID' => 1,
            'hello' => 'yes',
            'errno' => new class{},     // 会被header中的值覆盖
            'execTime' => 300,
        ]);
        $this->assertSame([
            'hello' => 'yes',
            'errno' => 1,
            'execTime' => 300,
            HTTPRequestV1Formatter::KEY_REQUEST => $request,
            HTTPRequestV1Formatter::KEY_IP => '2.2.2.2',
            HTTPRequestV1Formatter::KEY_USER_ID => '1',
            'response' => [
                'status' => 200,
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'body' => '{"data":"1"}'
            ],
        ], $logger->getLatestContext());

        /////////////////

        $reqLogger = new RequestLogger($logger, [
            'response' => [
                'logHeaders' => true,
                'logBody' => true,
            ],
        ]);
        $request = new Request("GET", '/', [
            'x-real-ip' => '9.9.9.9',
        ]);
        $response = new Response(200, [
            'content-type' => 'application/json',
        ], '{"data":"1"}');
        $reqLogger->log($request, $response, [
            'userID' => 1,
            'hello' => 'yes',
            'errno' => new class{},     // 会被header中的值覆盖
            'execTime' => '300ms',
        ]);
        $this->assertSame([
            'hello' => 'yes',
            'errno' => 1,
            'execTime' => 300,
            HTTPRequestV1Formatter::KEY_REQUEST => $request,
            HTTPRequestV1Formatter::KEY_IP => '9.9.9.9',
            HTTPRequestV1Formatter::KEY_USER_ID => '1',
            'response' => [
                'status' => 200,
                'headers' => [
                    'content-type' => 'application/json',
                ],
                'body' => '{"data":"1"}'
            ],
        ], $logger->getLatestContext());
    }
}