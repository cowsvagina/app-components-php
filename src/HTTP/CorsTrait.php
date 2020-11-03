<?php

declare(strict_types=1);

namespace NB\AppComponents\HTTP;

use Psr\Http\Message\ResponseInterface;

/**
 * @see https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Access_control_CORS 关于CORS参考此文档
 */
trait CorsTrait
{
    /**
     * @var string 指定Access-Control-Allow-Origin的值
     */
    protected string $allowOrigin = '';

    /**
     * @var array 指定Access-Control-Allow-Headers的值
     */
    protected array $allowHeaders = [];

    /**
     * @var array 指定Access-Control-Allow-Methods的值
     */
    protected array $allowMethods = [];

    /**
     * @var bool 指定Access-Control-Allow-Credentials的值
     */
    protected bool $allowCredentials = false;

    /**
     * @var array 指定Access-Control-Expose-Headers的值
     */
    protected array $exposeHeaders = [];

    /**
     * @var int 指定Access-Control-Max-Age的值
     */
    protected int $maxAge = 0;

    private static string $headerAllowOrigin = 'Access-Control-Allow-Origin';
    private static string $headerAllowHeaders = 'Access-Control-Allow-Headers';
    private static string $headerAllowMethods = 'Access-Control-Allow-Methods';
    private static string $headerAllowCredential = 'Access-Control-Allow-Credentials';
    private static string $headerExposeHeaders = 'Access-Control-Expose-Headers';
    private static string $headerMaxAge = 'Access-Control-Max-Age';

    private string $cachedAllowOrigin = '';
    private array $cachedAllowHeaders = [];
    private array $cachedAllowMethods = [];
    private bool $cachedAllowCredentials = false;
    private array $cachedExposeHeaders = [];
    private int $cachedMaxAge = 0;

    protected function withCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        if ($this->allowOrigin) {
            $response = $response->withHeader(self::$headerAllowOrigin, $this->allowOrigin);
        }

        if ($this->allowMethods) {
            $response = $response->withHeader(self::$headerAllowMethods, implode(", ", $this->allowMethods));
        }

        if ($this->allowHeaders) {
            $response = $response->withHeader(self::$headerAllowHeaders, implode(", ", $this->allowHeaders));
        }

        if ($this->allowCredentials) {
            $response = $response->withHeader(self::$headerAllowCredential, 'true');
        }

        if ($this->exposeHeaders) {
            $response = $response->withHeader(self::$headerExposeHeaders, implode(", ", $this->exposeHeaders));
        }

        if ($this->maxAge > 0) {
            $response = $response->withHeader(self::$headerMaxAge, $this->maxAge);
        }

        return $response;
    }

    final protected function cachedCurrentSettings()
    {
        $this->cachedAllowOrigin = $this->allowOrigin;
        $this->cachedAllowMethods = $this->allowMethods;
        $this->cachedAllowHeaders = $this->allowHeaders;
        $this->cachedAllowCredentials = $this->allowCredentials;
        $this->cachedExposeHeaders = $this->exposeHeaders;
        $this->cachedMaxAge = $this->maxAge;
    }

    final protected function clearSettings()
    {
        $this->allowOrigin = '';
        $this->allowMethods = [];
        $this->allowHeaders = [];
        $this->allowCredentials = false;
        $this->exposeHeaders = [];
        $this->maxAge = 0;
    }

    final protected function restoreSettings()
    {
        $this->allowOrigin = $this->cachedAllowOrigin;
        $this->allowMethods = $this->cachedAllowMethods;
        $this->allowHeaders = $this->cachedAllowHeaders;
        $this->allowCredentials = $this->cachedAllowCredentials;
        $this->exposeHeaders = $this->cachedExposeHeaders;
        $this->maxAge = $this->cachedMaxAge;
    }
}