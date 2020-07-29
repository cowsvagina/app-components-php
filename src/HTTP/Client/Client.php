<?php

declare(strict_types=1);

namespace NB\AppComponents\HTTP\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    /**
     * @var array 记录了最后一次请求的相关信息
     */
    protected array $lastRequestInfo = [
        /** @var RequestInterface|null */
        'request' => null,

        /** @var ResponseInterface|null */
        'response' => null,

        /** @var \Throwable|null */
        'exception' => null,

        /** @var float 发送请求前的时间戳(通过microtime(true)获得,带小数表示毫微纳秒) */
        'timeBeforeRequest' => 0,

        /** @var float 收到响应后的时间戳(通过microtime(true)获得,带小数表示毫微纳秒) */
        'timeAfterRespond' => 0,
    ];

    private ?Logger $logger;

    private array $options = [
        'recordingMiddlewareName' => 'http_request_info_recording',
        'logExtra' => [],
    ];

    private bool $middlewareRegistered = false;

    /**
     * @param array $config GuzzleHTTP的配置信息
     * @param Logger|null $logger
     * @param array $options [
     *      'recordingMiddlewareName' => (string),          // Client指定中间件名称
     *      'logExtra' => (array),                          // 记录日志的额外信息
     * ]
     */
    public function __construct(array $config = [], Logger $logger = null,  array $options = [])
    {
        parent::__construct($config);

        $this->logger = $logger;
        $this->options = array_merge($this->options, $options);
        if (!is_array($this->options['logExtra'])) {
            // 保证logExtra是数组类型
            $this->options['logExtra'] = [];
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->setLastRequestInfo($request);
        $this->prepareMiddleware();

        return parent::sendRequest($request);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $this->setLastRequestInfo($request);
        $this->prepareMiddleware();

        return parent::sendAsync($request, $options);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $this->setLastRequestInfo($request);
        $this->prepareMiddleware();

        return parent::send($request, $options);
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $this->resetLastRequestInfo();
        $this->prepareMiddleware();

        return parent::request($method, $uri, $options);
    }

    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        $this->resetLastRequestInfo();
        $this->prepareMiddleware();

        return parent::requestAsync($method, $uri, $options);
    }

    public function setLogger(?Logger $logger)
    {
        $this->logger = $logger;
    }

    protected function prepareMiddleware()
    {
        if ($this->middlewareRegistered) {
            return;
        }

        $config = $this->getConfig();
        /** @var HandlerStack $stack */
        $stack = $config['handler'];
        $stack->unshift(function($handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $timeBeforeRequest = microtime(true);
                /** @var PromiseInterface $promise */
                $promise = $handler($request, $options);
                $promise = $promise->then(function (ResponseInterface $response = null) use ($request, $timeBeforeRequest) {
                    $timeAfterRespond = microtime(true);
                    $this->setLastRequestInfo($request, $response, null, $timeBeforeRequest, $timeAfterRespond);

                    if ($this->logger) {
                        $this->logger->log($request, $response, array_merge($this->options['logExtra'], [
                            'timeBeforeRequest' => $timeBeforeRequest,
                            'timeAfterRespond' => $timeAfterRespond,
                        ]));
                    }

                    return $response;
                }, function(\Throwable $e = null) use ($request, $timeBeforeRequest) {
                    $response = null;
                    if ($e instanceof RequestException) {
                        $response = $e->getResponse();
                    }

                    $timeAfterRespond = microtime(true);
                    $this->setLastRequestInfo($request, $response, $e, $timeBeforeRequest, $timeAfterRespond);

                    if ($this->logger) {
                        $this->logger->log($request, $response, array_merge($this->options['logExtra'], [
                            'timeBeforeRequest' => $timeBeforeRequest,
                            'timeAfterRespond' => $timeAfterRespond,
                            'exception' => $e,
                        ]));
                    }

                    throw $e;
                });

                return $promise;
            };
        }, $this->options['recordingMiddlewareName']);

        $this->middlewareRegistered = true;
    }

    protected function resetLastRequestInfo()
    {
        $this->setLastRequestInfo();
    }

    /**
     * @param RequestInterface|null $request
     * @param ResponseInterface|null $response
     * @param \Throwable|null $exception
     * @param float $timeBeforeRequest
     * @param float $timeAfterRespond
     */
    protected function setLastRequestInfo(
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        ?\Throwable $exception = null,
        float $timeBeforeRequest = 0,
        float $timeAfterRespond = 0
    ) {
        $this->lastRequestInfo['request'] = $request;
        $this->lastRequestInfo['response'] = $response;
        $this->lastRequestInfo['exception'] = $exception;
        $this->lastRequestInfo['timeBeforeRequest'] = $timeBeforeRequest;
        $this->lastRequestInfo['timeAfterRespond'] = $timeAfterRespond;
    }
}