<?php

declare(strict_types=1);

namespace NB\AppComponents\Framework\Hyperf\Middleware;

use Hyperf\Utils\Context;
use NB\AppComponents\HTTP\CorsTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 继承该中间件,通过设置CorsTrait中的变量来决定下行的Cors头.
 *
 * 有两种方式指定下行cors头内容.
 *  1. 继承BaseCorsMiddle之后直接覆盖CorsTrait中的相关变量. 此时下行的cors头是都是固定值
 *  2. 重写overrideCorsSettings方法,这里可以根据请求的不同,定制化的设置cors头内容
 */
class BaseCorsMiddleware implements MiddlewareInterface
{
    use CorsTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::get(ResponseInterface::class);

        $this->overrideCorsSettings($request);
        $response = $this->withCorsHeaders($response);
        $this->afterSetCorsHeaders($request, $response);

        Context::set(ResponseInterface::class, $response);

        if ($request->getMethod() == 'OPTIONS') {
            return $response;
        }

        return $handler->handle($request);
    }

    protected function overrideCorsSettings(ServerRequestInterface $request)
    {

    }

    /**
     * 可以用于清理资源/恢复配置/打印日志等操作.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    protected function afterSetCorsHeaders(ServerRequestInterface $request, ResponseInterface $response)
    {

    }
}