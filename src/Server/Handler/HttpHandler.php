<?php
declare(strict_types=1);
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2020
 *
 * @see      https://www.github.com/fastdlabs
 * @see      http://www.fastdlabs.com/
 */

namespace FastD\Server\Handler;


use FastD\Http\SwooleRequest;
use FastD\Routing\Exceptions\RouteException;
use FastD\Routing\Exceptions\RouteNotFoundException;
use FastD\Swoole\Server\Handler\HandlerAbstract;
use FastD\Swoole\Server\Handler\HTTPHandlerInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * swoole http 处理器，接受的http请求会使用该对象进行处理
 *
 * Class HttpHandle
 * @package FastD\Server\Handle
 */
class HttpHandler extends HandlerAbstract implements HTTPHandlerInterface
{
    public function onRequest(Request $swooleRequet, Response $swooleResponse): void
    {
        try {
            $request = SwooleRequest::createServerRequestFromSwoole($swooleRequet);
            if ($request->serverParams['PATH_INFO'] === '/favicon.ico') {
                $swooleResponse->end();
                return;
            }
            $response = app()->dispatch($request);
        } catch (RouteNotFoundException $e) {
            $exceptionData = runtime()->handleException(new RouteException(\FastD\Http\Response::$statusTexts[\FastD\Http\Response::HTTP_FORBIDDEN], \FastD\Http\Response::HTTP_FORBIDDEN));
            $response = json($exceptionData, \FastD\Http\Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            $exceptionData = runtime()->handleException($e);
            $response = json($exceptionData, \FastD\Http\Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        foreach ($response->getHeaders() as $key => $header) {
            $swooleResponse->header($key, $response->getHeaderLine($key));
        }
        foreach ($response->getCookies() as $key => $cookie) {
            $swooleResponse->cookie($key, $cookie);
        }
        $swooleResponse->status($response->getStatusCode());
        $swooleResponse->end((string) $response->getBody());
    }
}
