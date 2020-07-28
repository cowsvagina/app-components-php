<?php

declare(strict_types=1);

namespace NB\AppComponents\HTTP\Client;

use GuzzleHttp\Exception\RequestException;
use NB\AppComponents\HTTP\Helper;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Logger
{
    private LoggerInterface $logger;

    private array $options = [
        'message' => 'http_client_request',             // 日志message的值
        'failingLogMsg' => 'http_client_failing_log',   // 提取日志信息出错时的补充日志message
        'logRequestHeaders' => true,                    // 是否记录请求header
        'logResponseHeaders' => true,                   // 是否记录响应header
        'logExceptionTrace' => true,                    // 是否记录异常trace
        'logRequestBodyTypes' => [                      // 指定哪些类型的请求体内容需要被记录下来, application/x-www-form-urlencoded类型会被解析为数组形式
            'application/json',
            'application/xml',
            'application/x-www-form-urlencoded',
        ],
        'logResponseBodyTypes' => [                     // 指定哪些类型的响应体内容需要被记录下来
            'application/json',
            'application/xml',
        ],
        'requestRecvTimeHeader' => '',                  // 响应中的header名,该header记录了远端<收到>请求时的毫秒时间戳,用于粗算网络上行耗时,该header的值必须为毫秒时间戳
        'responseSentTimeHeader' => '',                 // 响应中的header名,该header记录了远端<发送>响应时的毫秒时间戳,用于粗算网络下行耗时,该header的值必须为毫秒时间戳
        'logExtra' => [],                               // 该字段允许预设一些信息,在每一次调用log方法时,这些都会成为extra信息中的一部分,它们可以被log方法的$extra参数中的信息覆盖
    ];

    public function __construct(LoggerInterface $logger, array $options = [])
    {
        $this->logger = $logger;
        $this->options = array_merge($this->options, $options);
        if (!is_array($this->options['logExtra'])) {
            // logExtra必须为数组
            $this->options['logExtra'] = [];
        }
    }

    /**
     * 记录日志.
     *
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     * @param array $extra 一些预定的字段,用于记录特殊信息 [
     *      exception: (\Throwable),            // 请求产生的异常
     *      timeBeforeRequest: (float|int),     // 请求前时间戳(允许带小数表示毫微纳秒)
     *      timeAfterRespond: (float|int),      // 响应后时间戳(允许带小数表示毫微纳秒)
     * ]
     */
    public function log(RequestInterface $request, ?ResponseInterface $response, array $extra = [])
    {
        try {
            $logContext = [
                'request' => $this->extraRequestInfo($request),
            ];

            if ($response) {
                $logContext['response'] = $this->extractResponseInfo($response);
            }

            // 提取耗时信息
            if ($costs = $this->extractCosts($response, $extra)) {
                $logContext['costs'] = $costs;
            }

            $exception = $extra['exception'] ?? null;
            if (!($exception instanceof \Throwable)) {
                $exception = null;
            }

            unset($extra['timeBeforeRequest'], $extra['timeAfterRespond'], $extra['exception']);
            $extra = array_merge($this->options['logExtra'], $extra);

            // 提取错误中的curl信息
            if ($curlInfo = $this->extractCurlInfo($exception)) {
                $logContext['curl'] = $curlInfo;
            }

            // 提取异常消息和trace信息
            if ($exceptionInfo = $this->extractExceptionInfo($exception)) {
                $logContext['exception'] = $exceptionInfo;
            }

            if ($response instanceof ResponseInterface) {
                $logLevel = $response->getStatusCode() >= 400 ? 'error' : 'info';
            } else if ($exception instanceof \Throwable) {
                $logLevel = 'error';
            } else {
                // 没有异常,又没有响应?
                $logLevel = 'warning';
            }
        } catch (\Throwable $exception) {
            unset($extra['timeBeforeRequest'], $extra['timeAfterRespond'], $extra['exception']);
            $extra = array_merge($this->options['logExtra'], $extra);
            $this->logger->error($this->options['failingLogMsg'], array_merge($extra, [
                'error' => [
                    'msg' => $exception->getMessage(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ],
            ]));
            return;
        }

        $this->logger->log($logLevel, $this->options['message'], array_merge($extra, $logContext));
    }

    protected function extraRequestInfo(RequestInterface $request): array
    {
        $uri = $request->getUri();
        $info = [
            'method' => $request->getMethod(),
            'scheme' => $uri->getScheme(),
            'host' => $uri->getHost(),
            'path' => $uri->getPath(),
        ];

        if ($this->options['logRequestHeaders'] && $headers = $this->getHeaders($request)) {
            $info['headers'] = $headers;
        }

        if ($query = Helper::parseQuery($uri->getQuery(), PHP_QUERY_RFC3986)) {
            $info['query'] = $query;
        }

        $data = $this->extractRequestBody($request);
        $info = array_merge($info, $data);

        return $info;
    }

    protected function extractResponseInfo(ResponseInterface $response): array
    {
        $info = [
            'status' => $response->getStatusCode(),
        ];

        if ($this->options['logResponseHeaders'] && $headers = $this->getHeaders($response)) {
            $info['headers'] = $headers;
        }

        $body = $this->extraResponseBody($response);
        if ($body) {
            $info['body'] = $this->extraResponseBody($response);
        }

        return $info;
    }

    protected function extractCurlInfo(?\Throwable $e): array
    {
        if (!($e instanceof RequestException)) {
            return [];
        }

        $handlerContext = $e->getHandlerContext();
        if (isset($handlerContext['errno'])) {
            $info = [
                'errno' => intval($handlerContext['errno']),
                'error' => strval($handlerContext['error'] ?? ''),
            ];
        }

        return $info ?? [];
    }

    protected function extractExceptionInfo(?\Throwable $e): array
    {
        if (!$e || $e instanceof RequestException) {
            return [];
        }

        $info = [
            'msg' => $e->getMessage(),
        ];
        if ($this->options['logExceptionTrace']) {
            $info['trace'] = explode("\n", $e->getTraceAsString());
        }

        return $info;
    }

    protected function extractCosts(?ResponseInterface $response, array &$extra): array
    {
        $costs = [];
        $timeBeforeRequest = intval($extra['timeBeforeRequest'] ?? 0);
        $timeAfterRespond = intval($extra['timeAfterRespond'] ?? 0);

        if ($timeAfterRespond > 0 && $timeBeforeRequest > 0) {
            $costs['total'] = $timeAfterRespond - $timeBeforeRequest;
        }

        if (!$response) {
            return $costs;
        }

        // 上行网络耗时
        if ($this->options['requestRecvTimeHeader']) {
            $requestReceivedTime = $response->getHeaderLine($this->options['requestRecvTimeHeader']);
            if ($timeBeforeRequest > 0 && is_numeric($requestReceivedTime)) {
                $costs['upstream'] = (intval($requestReceivedTime) / 1000) - (intval($timeBeforeRequest * 1000) / 1000);
            }
        }

        // 下行网络耗时
        if ($this->options['responseSentTimeHeader']) {
            $responseSentTime = $response->getHeaderLine($this->options['responseSentTimeHeader']);
            if ($timeAfterRespond > 0 && is_numeric($responseSentTime)) {
                $costs['downstream'] = (intval($timeAfterRespond * 1000) / 1000) - intval($responseSentTime) / 1000;
            }
        }

        return $costs;
    }

    protected function extractRequestBody(MessageInterface $r): array
    {
        $data = [];

        $type = $r->getHeaderLine('content-type');
        foreach ($this->options['logRequestBodyTypes'] as $each) {
            if (stripos($type, $each) !== false) {
                $body = strval($r->getBody());
                if (strtolower($each) === 'application/x-www-form-urlencoded') {
                    $postForm = Helper::parseQuery($body, PHP_QUERY_RFC1738);
                    if ($postForm) {
                        $data['postForm'] = $postForm ?: new class{};
                    }
                } else {
                    if ($data) {
                        $data['body'] = $body ?: '';
                    }
                }
                break;
            }
        }

        return $data;
    }

    protected function extraResponseBody(?ResponseInterface $response): string
    {
        $body = '';

        $type = $response->getHeaderLine('content-type');
        foreach ($this->options['logResponseBodyTypes'] as $each) {
            if (stripos($type, $each) !== false) {
                $body = strval($response->getBody());
                break;
            }
        }

        return $body;
    }

    protected function getHeaders(MessageInterface $r): array
    {
        $headers = [];
        foreach ($r->getHeaders() as $name => $_) {
            $headers[$name] = $r->getHeaderLine($name);
        }

        return $headers;
    }
}
