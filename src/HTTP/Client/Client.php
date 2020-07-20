<?php

declare(strict_types=1);

namespace NB\HTTP\Client;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    private ?Logger $logger;

    public function __construct(array $config = [], Logger $logger = null)
    {
        parent::__construct($config);

        $this->logger = $logger;
        $config = $this->getConfig();
        /** @var HandlerStack $stack */
        $stack = $config['stack'];
        $stack->push(function($handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $timeBeforeRequest = microtime(true);
                /** @var PromiseInterface $promise */
                $promise = $handler($request, $options);
                $promise->then(function (ResponseInterface $response = null) use ($request, $timeBeforeRequest, $options) {
                    $this->logger->log($request, $response, array_merge($options['loggingInfo'], [
                        'timeBeforeRequest' => $timeBeforeRequest,
                        'timeAfterRespond' => microtime(true),
                    ]));

                    return $response;
                }, function(\Throwable $e = null) use ($request, $timeBeforeRequest, $options) {
                    $response = null;
                    if ($e instanceof RequestException) {
                        $response = $e->getResponse();
                    }
                    $this->logger->log($request, $response, array_merge($options['loggingInfo'], [
                        'timeBeforeRequest' => $timeBeforeRequest,
                        'timeAfterRespond' => microtime(true),
                        'exception' => $e,
                    ]));

                    throw $e;
                });
            };
        }, 'http_request_logging');
    }
}