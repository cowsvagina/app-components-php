<?php

declare(strict_types=1);

/**
 * use NB\AppComponents\Logging\Monolog\Formatters\HTTPRequestV1Formatter;
 *
 * $logger = new \Monolog\Logger('request');.
 * $formatter = new PsrRequestFormatter('service', 'environment');.
 *
 * $formatter->setHeadersWhitelist(['x-abc', 'x-def']);
 * // or
 * $formatter->setHeadersBlacklist(['accept', 'accept-language']);
 *
 * $handler = new \Monolog\Handler\StreamHandler('php://output');
 * $handler->setFormatter($formatter);
 * $logger->pushHandler($handler);
 *
 * $logger->info('', [
 *     PsrRequestFormatter::KEY_REQUEST = $req,
 *     PsrRequestFormatter::KEY_IP = $ip,
 *     PsrRequestFormatter::KEY_USER = $user,
 *
 *     'runtime' => 20,
 *     'other' => '..',
 * ]);
 */

namespace NB\AppComponents\Logging\Monolog\Formatters;

use Monolog\Formatter\FormatterInterface;
use NB\AppComponents\Logging\Exceptions\IllegalServiceException;
use Psr\Http\Message\ServerRequestInterface;

class HTTPRequestV1Formatter implements FormatterInterface
{
    use HelperTrait;

    const SCHEMA = 'http.request.v1';

    const KEY_REQUEST = '__REQUEST__';
    const KEY_IP = '__IP__';
    const KEY_USER = '__USER__';
    const KEY_RUNTIME = '__RUNTIME__';

    protected string $service;
    protected string $environment;

    protected array $headers_blacklist = [];
    protected array $headers_whitelist = [];

    public function __construct(string $service, string $environment)
    {
        if (empty($service)) {
            throw new IllegalServiceException();
        }

        $this->service = $service;
        $this->environment = $environment;
    }

    public function setHeadersBlacklist(array $keys)
    {
        $this->headers_blacklist = $keys;

        return $this;
    }

    public function setHeadersWhitelist(array $keys)
    {
        $this->headers_whitelist = $keys;

        return $this;
    }

    /**
     * Return formatted request log as json string.
     *
     * {@inheritdoc}
     *
     * @return string
     */
    public function format(array $record)
    {
        $emptyObj = new class{};

        $context = $record['context'] ?? [];
        if (!is_array($context)) {
            $type = $this->getType($context);
            $context = [];
            $context['ctxErr'][] = [
                'msg' => 'invalid context type in log data',
                'type' => $type,
            ];
        }

        $ip = $context[self::KEY_IP] ?? null;
        if (is_string($ip)) {
            $context['ip'] = $ip;
        } else if ($ip !== null) {
            $context['ctxErr'][] = [
                'msg' => 'invalid type of ip field',
                'type' => $this->getType($ip),
            ];
        }
        unset($context[self::KEY_IP]);

        $user = $context[self::KEY_USER] ?? null;
        if (is_numeric($user) || is_string($user)) {
            $context['user'] = strval($user);
        } else if ($user !== null) {
            $context['ctxErr'][] = [
                'msg' => 'invalid type of user field',
                'type' => $this->getType($user),
            ];
        }
        unset($context[self::KEY_USER]);

        $req = $context[self::KEY_REQUEST] ?? null;
        if (!($req instanceof ServerRequestInterface)) {
            $context['ctxErr'][] = [
                'msg' => 'request object is not a PSR-7 request',
                'type' => $this->getType($req),
            ];
            $req = null;
        }
        unset($context[self::KEY_REQUEST]);

        $runtime = $context[self::KEY_RUNTIME] ?? null;
        if (is_numeric($runtime)) {
            $context['runtime'] = floatval($runtime);
        } else if ($runtime !== null) {
            $context['ctxErr'][] = [
                'msg' => 'invalid type of runtime field',
                'type' => $this->getType($runtime),
            ];
        }
        unset($context[self::KEY_RUNTIME]);

        $dt = $record['datetime'] ?? new \DateTime();
        if (!($dt instanceof \DateTimeInterface)) {
            $dt = new \DateTime();
        }

        $data = [
            'schema' => 'http.request.v1',
            'service' => $this->service,
            'environment' => $this->environment,
            'time' => $dt->format('Y-m-d\TH:i:s.uP'),
            'method' => $req ? \strtoupper($req->getMethod()) : '',
            'path' => $req ? $req->getUri()->getPath() : '',
            'headers' => $req ? $this->pickHeaders($req) : $emptyObj,
            'get' => $req ? ($req->getQueryParams() ?: $emptyObj) : $emptyObj,
            'post' => $emptyObj,
            'context' => $context,
        ];

        if ($req !== null && !in_array($data['method'], ['GET', 'HEAD'])) {
            try {
                $post = $req->getParsedBody();
                if (is_array($post) || is_object($post)) {
                    $data['post'] = $post;
                }
            } catch (\Throwable $e) {
                $data['context']['bodyParsedError'] = [
                    'msg' => $e->getMessage(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ];
            }
        }

        if (empty($data['context'])) {
            $data['context'] = $emptyObj;
        }

        $options = JSON_UNESCAPED_SLASHES;
        $result = \json_encode($data, $options);

        return "{$result}\n";
    }

    public function formatBatch(array $records)
    {
        throw new \Exception('not implement');
    }

    protected function pickHeaders(ServerRequestInterface $req)
    {
        $headers = [];
        foreach ($req->getHeaders() as $key => $val) {
            $headers[$key] = $req->getHeaderLine($key);
        }

        foreach ($this->headers_blacklist as $key) {
            unset($headers[$key]);
        }

        if ($this->headers_whitelist) {
            $h = [];
            foreach ($this->headers_whitelist as $key) {
                if (isset($headers[$key])) {
                    $h[$key] = $headers[$key];
                }
            }
            $headers = $h;
        }

        return $headers ?: new class{};
    }
}
