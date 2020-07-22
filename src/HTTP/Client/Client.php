<?php

declare(strict_types=1);

namespace NB\Components\HTTP\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    private ?Logger $logger;

    private bool $registeredLogging = false;

    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct($config);

        $this->logger = $logger;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->prepareLoggingMiddleware();

        return parent::sendRequest($request);
    }

    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        $this->prepareLoggingMiddleware();

        return parent::sendAsync($request, $options);
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $this->prepareLoggingMiddleware();

        return parent::send($request, $options);
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $this->prepareLoggingMiddleware();

        return parent::request($method, $uri, $options);
    }

    public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface
    {
        $this->prepareLoggingMiddleware();

        return parent::requestAsync($method, $uri, $options);
    }

    public function setLogger(?Logger $logger)
    {
        $this->logger = $logger;
    }

    protected function prepareLoggingMiddleware()
    {
        if ($this->registeredLogging) {
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
                $promise = $promise->then(function (ResponseInterface $response = null) use ($request, $timeBeforeRequest, $options) {
                    if ($this->logger) {
                        $this->logger->log($request, $response, array_merge($options['loggingContext'], [
                            'timeBeforeRequest' => $timeBeforeRequest,
                            'timeAfterRespond' => microtime(true),
                        ]));
                    }

                    return $response;
                }, function(\Throwable $e = null) use ($request, $timeBeforeRequest, $options) {
                    if ($this->logger) {
                        $response = null;
                        if ($e instanceof RequestException) {
                            $response = $e->getResponse();
                        }

                        $this->logger->log($request, $response, array_merge($options['loggingContext'], [
                            'timeBeforeRequest' => $timeBeforeRequest,
                            'timeAfterRespond' => microtime(true),
                            'exception' => $e,
                        ]));
                    }

                    throw $e;
                });

                return $promise;
            };
        }, 'http_request_logging');

        $this->registeredLogging = true;
    }
}