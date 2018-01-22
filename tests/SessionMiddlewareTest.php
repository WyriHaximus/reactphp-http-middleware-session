<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;

final class SessionMiddlewareTest extends TestCase
{
    public function provideCookieLines()
    {
        yield [
            [
                10,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
            ],
        ];

        yield [
            [
                10,
                '/example/',
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
                true,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
                'secure',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
                false,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
                true,
                true,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
                'secure',
                'httponly',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
                false,
                false,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
            ],
        ];

        yield [
            [
                10,
                '/example/',
                'www.example.com',
                false,
                true,
            ],
            [
                'expires=Thu, 01-Jan-1970 00:00:10 GMT',
                'path=/example/',
                'domain=www.example.com',
                'httponly',
            ],
        ];
    }

    /**
     * @dataProvider provideCookieLines
     */
    public function testSetCookieLine(array $cookieParams, array $cookieLineChunks)
    {
        $next = function (ServerRequestInterface $request) {
            return new Response();
        };

        $cache = new ArrayCache();
        $middleware = new SessionMiddleware('Elmo', $cache, $cookieParams);

        /** @var ResponseInterface $response */
        $response = $this->await($middleware(new ServerRequest('GET', 'https://www.example.com'), $next));

        $cookieChunks = explode('; ', $response->getHeaderLine('Set-Cookie'));
        array_shift($cookieChunks);

        self::assertSame($cookieLineChunks, $cookieChunks);
    }

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
