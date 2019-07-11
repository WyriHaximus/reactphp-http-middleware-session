<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use function React\Promise\reject;
use function React\Promise\resolve;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;

/**
 * @internal
 */
final class SessionMiddlewareTest extends AsyncTestCase
{
    /** @var InspectableArrayCache */
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new InspectableArrayCache();
    }

    public function provideCookieLines(): iterable
    {
        yield [
            function () {
                return [
                    [
                        10,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                        true,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                        'secure',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                        false,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                        true,
                        true,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                        'secure',
                        'httponly',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                        false,
                        false,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                    ],
                ];
            },
        ];

        yield [
            function () {
                return [
                    [
                        10,
                        '/example/',
                        'www.example.com',
                        false,
                        true,
                    ],
                    [
                        'expires=' . \gmdate('D, d-M-Y H:i:s T', \time() + 10),
                        'path=/example/',
                        'domain=www.example.com',
                        'httponly',
                    ],
                ];
            },
        ];
    }

    /**
     * @dataProvider provideCookieLines
     */
    public function testSetCookieLine(callable $setup): void
    {
        self::waitUntilTheNextSecond();

        [$cookieParams, $cookieLineChunks] = $setup();

        $next = function (ServerRequestInterface $request) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();

            return new Response();
        };

        $middleware = new SessionMiddleware('Elmo', $this->cache, $cookieParams);

        /** @var ResponseInterface $response */
        $response = $this->await($middleware(new ServerRequest('GET', 'https://www.example.com'), $next));

        $cookieChunks = \explode('; ', $response->getHeaderLine('Set-Cookie'));
        \array_shift($cookieChunks);

        self::assertSame($cookieLineChunks, $cookieChunks);
    }

    public function testSessionDoesntExistsAndNotStartingOne(): void
    {
        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ));
        $next = function (ServerRequestInterface $request) use (&$session) {
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $middleware($request, $next);

        self::assertCount(0, $this->cache->getData());
        self::assertFalse($session->isActive());
    }

    public function testSessionDoesntExistsAndStartingOne(): void
    {
        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ));
        $session = null;
        $next = function (ServerRequestInterface $request) use (&$session) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->setContents([
                'foo' => 'bar',
            ]);
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $middleware($request, $next);

        self::assertCount(1, $this->cache->getData());
        self::assertTrue($session->isActive());
        self::assertSame([
            $session->getId() => [
                'foo' => 'bar',
            ],
        ], $this->cache->getData());
    }

    public function testSessionExistsAndKeepingItAlive(): void
    {
        $contents = ['Sand'];
        $cookieName = 'CookieMonster';
        $this->cache->set('cookies', ['Chocolate Chip']);
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            $cookieName => 'cookies',
        ]);

        $session = null;
        $next = function (ServerRequestInterface $request) use ($contents, &$session) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->setContents($contents);
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        /** @var ResponseInterface $response */
        $response = $this->await($middleware($request, $next));

        $sandCoookies = $this->await($this->cache->get('cookies'));

        self::assertCount(1, $this->cache->getData());
        self::assertTrue($session->isActive());
        self::assertSame($contents, $sandCoookies);
        self::assertSame($cookieName . '=cookies', $response->getHeaderLine('Set-Cookie'));
    }

    public function testSessionExistsAndEndingIt(): void
    {
        $cookieName = 'CookieMonster';
        $cache = new ArrayCache();
        $cache->set('cookies', ['Chocolate Chip']);
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            $cookieName => 'cookies',
        ]);

        $session = null;
        $next = function (ServerRequestInterface $request) use (&$session) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->end();
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        /** @var ResponseInterface $response */
        $response = $this->await($middleware($request, $next));

        self::assertCount(0, $this->cache->getData());
        self::assertFalse($session->isActive());
        self::assertSame($cookieName . '=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT', $response->getHeaderLine('Set-Cookie'));
    }

    public function testUpdateCacheDeletesOldIds(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get(Argument::any())->shouldNotBeCalled();
        $cache->set(Argument::any(), Argument::any())->shouldBeCalled();
        $cache->delete(Argument::any())->shouldBeCalled()->willReturn(resolve(true));

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache->reveal());

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/'
        );

        $next = function (ServerRequestInterface $request) {
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->regenerate();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->regenerate();

            return new Response();
        };

        $this->await($middleware($request, $next));
    }

    public function testASessionIdIsAlwaysCheckedForInTheCache(): void
    {
        $cache = $this->prophesize(CacheInterface::class);
        $cache->get('cookies')->shouldBeCalled();
        $cache->set(Argument::any(), Argument::any())->shouldBeCalled();

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache->reveal());

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            $cookieName => 'cookies',
        ]);

        $next = function () {
            return new Response();
        };

        $this->await($middleware($request, $next));
    }

    public function testAnErrorFromTheCacheShouldBubbleUp(): void
    {
        self::expectException(\Exception::class);
        self::expectExceptionMessage('Error on the cache layer');

        $cache = $this->prophesize(CacheInterface::class);
        $cache->get('cookies')->shouldBeCalled()->willReturn(reject(new \Exception('Error on the cache layer')));

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache->reveal());

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            $cookieName => 'cookies',
        ]);

        $next = function () {
            return new Response();
        };

        $this->await($middleware($request, $next));
    }

    public function provideHeaderExpiresCombos(): iterable
    {
        yield [
            function () {
                return [
                    0,
                    '',
                ];
            },
        ];

        yield [
            function () {
                $t = 1;

                return [
                    $t,
                    \sprintf(
                        '; expires=%s',
                        \gmdate('D, d-M-Y H:i:s T', \time() + $t)
                    ),
                ];
            },
        ];

        yield [
            function () {
                $t = 60 * 5;

                return [
                    $t,
                    \sprintf(
                        '; expires=%s',
                        \gmdate('D, d-M-Y H:i:s T', \time() + $t)
                    ),
                ];
            },
        ];

        yield [
            function () {
                $t = 60 * 60 * 24 * 31;

                return [
                    $t,
                    \sprintf(
                        '; expires=%s',
                        \gmdate('D, d-M-Y H:i:s T', \time() + $t)
                    ),
                ];
            },
        ];
    }

    /**
     * @dataProvider provideHeaderExpiresCombos
     */
    public function testCookiesExpiresBasedOnConfiguration(callable $cookieMonster): void
    {
        self::waitUntilTheNextSecond();

        [$expires, $cookieHeaderSuffix] = $cookieMonster();

        $cookieName = 'CookieMonster';
        $cookieValue = 'cookies';
        $middleware = new SessionMiddleware($cookieName, $this->cache, [$expires]);

        $request = (new ServerRequest(
            'GET',
            'https://www.example.com/'
        ))->withCookieParams([
            $cookieName => $cookieValue,
        ]);

        $next = function () {
            return new Response();
        };

        /** @var ResponseInterface $response */
        $response = $this->await($middleware($request, $next));

        self::assertSame(
            [
                'Set-Cookie' => [
                    $cookieName . '=' . $cookieValue . $cookieHeaderSuffix,
                ],
            ],
            $response->getHeaders()
        );
    }
}
