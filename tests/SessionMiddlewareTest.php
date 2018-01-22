<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;

final class SessionMiddlewareTest extends TestCase
{
    public function testSessionExists()
    {
        $contents = ['Sand'];
        $cookieName = 'CookieMonster';
        $cache = new ArrayCache();
        $cache->set('cookies', ['Chocolate Chip']);
        $middleware = new SessionMiddleware($cookieName, $cache);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            'CookieMonster' => 'cookies',
        ]);

        $next = function (ServerRequestInterface $request) use ($contents) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->setContents($contents);

            return new Response();
        };

        $middleware($request, $next);

        $sandCoookies = $this->await($cache->get('cookies'));

        self::assertSame($contents, $sandCoookies);
    }
}
