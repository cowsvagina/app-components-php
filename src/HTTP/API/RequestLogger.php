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
     *                          user: (integer/string) 用户ID
     *                          errno: (integer) 错误码
     *                          execTime: (integer) 接口执行时间 (毫秒)
     *                     调用者应该尽可能保证每一次记录日志相同字段的数据类型始终保持一致,否则可能造成无法写入到ES的问题从而丢失日志
     */
    public function log(RequestInterface $request, ResponseInterface $response, array $extra = [])
    {
        $context = [
            HTTPRequestV1Formatter::KEY_REQUEST => $request,
            HTTPRequestV1Formatter::KEY_IP => $this->getRealIP($request),
            HTTPRequestV1Formatter::KEY_USER_ID => strval($extra['userID'] ?? ''),
            'response' => $this->getResponseInfo($response),
        ];

        if (isset($extra['errno'])) {
            // errno是一个预定义字段,专门表达错误码,所以这里如果是调用方传入的,会确保它的类型是正确的.
            $extra['errno'] = intval($extra['errno']);
        }

        if (isset($extra['execTime'])) {
            // execTime是一个预定义字段,专门表达执行时间(毫秒),所以这里如果是调用方传入的,会确保它的类型是正确的.
            $extra['execTime'] = intval($extra['execTime']);
        }

        unset($extra['userID']);

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

        return '';
    }
}