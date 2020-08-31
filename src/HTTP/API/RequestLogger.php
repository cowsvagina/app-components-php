<?php

declare(strict_types=1);

namespace NB\AppComponents\HTTP\API;

use NB\AppComponents\Logging\Monolog\Formatters\HTTPRequestV1Formatter;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * 请求日志记录类.
 * 提供一种在所有业务线中约定俗成的方式记录请求日志.
 *
 * 注意:
 *      使用这个类来记录日志,会默认使用方的log formatter用的是标准格式(http.request.v1).
 */
class RequestLogger
{
    private LoggerInterface $logger;

    private array $config = [
        'logMsg' => '',

        // 获取errno值的header名
        // 空字符串代表不记录
        // 日志中字段名固定为errno
        'errnoHeader' => '',

        // 获取请求执行耗时的header名(值的单位应该是毫秒)
        // 空字符串代表不记录
        // 日志中字段名固定为executionTime
        'executionTimeHeader' => '',

        // 如果客户端发送请求时在header中携带了发送时的时间戳,这个字段用来指定header名
        // 这个header中的值强制要求为毫秒时间戳
        'requestSentTimeHeader' => '',

        // 记录响应的配置
        'response' => [
            'logHeaders' => false,

            // 针对白名单临时方案,要么记录所有接口的下行,要么所有都不记录
            // 待whitelist功能实现后不再需要这个配置
            'logBody' => false,

            /**
             * TODO 尚未实现该功能
             * 记录请求体白名单,指定的接口才会记录响应体
             * whitelist格式: [
             *      ["{$method}", "{$path}"],
             *      ...
             * ]
             */
            'logBodyWhitelist' => [],
        ],
    ];

    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_replace_recursive($this->config, $config);
    }

    /**
     * 记录请求日志.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $extra 自定义信息, 有一些预订字段,有特殊入用途,如下:
     *                         userID: (integer/string) 用户ID
     *                         requestReceivedTime: (integer) 收到请求时的毫秒时间戳,该字段跟requestSentTimeHeader配置的header值一起用于计算客户端上行网络耗时
     *                     调用者应该尽可能保证每一次记录日志相同字段的数据类型始终保持一致,否则可能造成无法写入到ES的问题从而丢失日志
     */
    public function log(RequestInterface $request, ResponseInterface $response, array $extra = [])
    {
        $context = [
            HTTPRequestV1Formatter::KEY_REQUEST => $request,
            HTTPRequestV1Formatter::KEY_IP => $this->getRealIP($request),
            HTTPRequestV1Formatter::KEY_USER => strval($extra['userID'] ?? ''),
            'response' => $this->getResponseInfo($response),
        ];

        if ($this->config['errnoHeader']) {
            $context['errno'] = intval($response->getHeaderLine($this->config['errnoHeader']) ?: 0);
        } else if (isset($context['errno'])) {
            // errno是一个预定义字段,专门表达错误码,所以这里如果是调用方传入的,会确保它的类型是正确的.
            $context['errno'] = intval($context['errno']);
        }

        if ($this->config['executionTimeHeader']) {
            $context['executionTime'] = intval($response->getHeaderLine($this->config['executionTimeHeader']) ?: 0);
        } else if (isset($context['executionTime'])) {
            // executionTime是一个预定义字段,专门表达执行时间(毫秒),所以这里如果是调用方传入的,会确保它的类型是正确的.
            $context['executionTime'] = intval($context['executionTime']);
        }

        if ($this->config['requestSentTimeHeader'] && isset($extra['requestReceivedTime'])) {
            $sentTime = $request->getHeaderLine($this->config['requestSentTimeHeader']);
            $sentTime = $sentTime ? intval($sentTime) : $sentTime;

            if ($sentTime && $extra['requestReceivedTime']) {
                // upstreamCost是一个预定义字段,专门表达请求上行的网络耗时(毫秒)
                // 它根据请求体header中的携带的请求时间戳与收到请求时记录的时间戳做差值得到
                // 所以它的准确性严重依赖客户端和服务器端给出的时间的准确性
                $context['upstreamCost'] = intval($extra['requestReceivedTime']) - $sentTime;
            }
        }

        unset($extra['userID'], $extra['requestReceivedTime']);

        $this->logger->info($this->config['logMsg'], array_merge($extra, $context));
    }

    /**
     * @param ResponseInterface $response
     *
     * @return array
     */
    protected function getResponseInfo(ResponseInterface $response): array
    {
        $info = [
            'status' => $response->getStatusCode(),
        ];

        if ($this->config['response']['logHeaders']) {
            $info['headers'] = $this->getHeaders($response);
        }

        if ($this->config['response']['logBody']) {
            $info['body'] = strval($response->getBody());
        }

        return $info;
    }

    protected function getHeaders(MessageInterface $r): array
    {
        $headers = [];

        foreach ($r->getHeaders() as $name => $_) {
            $headers[$name] = $r->getHeaderLine($name);
        }

        return $headers;
    }

    protected function getRealIP(RequestInterface $request): string
    {
        if ($xff = $request->getHeaderLine("X-Forwarded-For")) {
            return trim(explode(',', $xff)[0]);
        }

        if ($xrip = $request->getHeaderLine("X-Real-IP")) {
            return $xrip;
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}