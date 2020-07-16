<?php

declare(strict_types=1);

namespace NB\HTTP\Client\Logging;

use NB\HTTP\Helper;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Logger
{
    private LoggerInterface $logger;

    private array $options = [];

    public function __construct(LoggerInterface $logger, array $options)
    {
        $this->logger = $logger;
        $this->options = $options;
    }

    /**
     * 记录日志.
     *
     * @param string $tag
     * @param RequestInterface $request
     * @param ResponseInterface|null $response
     * @param \Throwable|null $exception
     * @param array $extra [
     *      'timeBefore': (float|int),      // 请求前时间戳(允许带小数表示毫微纳秒)
     *      'timeAfter': (float|int),       // 响应后时间戳(允许带小数表示毫微纳秒)
     * ]
     */
    public function log(string $tag, RequestInterface $request, ?ResponseInterface $response, ?\Throwable $exception = null, array $extra = [])
    {

    }

    private function extractCosts(?ResponseInterface $response, array &$extra): array
    {
        $costs = [];
        $timeBefore = intval($extra['timeBefore'] ?? 0);
        $timeAfter = intval($extra['timeAfter'] ?? 0);

        if ($timeAfter > 0 && $timeBefore > 0) {
            $costs['total'] = $timeAfter - $timeBefore;
        }

        if (!$response) {
            return $costs;
        }

        // 上行网络耗时
        $requestReceivedTime = $response->getHeaderLine($this->options['reqRecvTimeHeader']);
        if ($timeBefore > 0 && is_numeric($requestReceivedTime)) {
            $logContext['upstream'] = (intval($requestReceivedTime) / 1000) - (intval($timeBefore * 1000) / 1000);
        }

        // 下行网络耗时
        $responseSentTime = $response->getHeaderLine($this->options['respSentTimeHeader']);
        if ($timeAfter > 0 && is_numeric($responseSentTime)) {
            $logContext['downstream'] = (intval($timeAfter * 1000) / 1000) - intval($responseSentTime) / 1000;
        }

        return $costs;
    }

    private function getHeaders(MessageInterface $r): array
    {
        $requestHeaders = [];
        foreach ($r->getHeaders() as $name => $_) {
            $requestHeaders[$name] = $r->getHeaderLine($name);
        }

        return $requestHeaders;
    }
}
