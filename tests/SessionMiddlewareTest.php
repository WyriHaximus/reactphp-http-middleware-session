<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use Exception;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use Throwable;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\Session;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;

use function array_shift;
use function assert;
use function explode;
use function gmdate;
use function React\Async\await;
use function React\Promise\reject;
use function React\Promise\resolve;
use function sprintf;

final class SessionMiddlewareTest extends AsyncTestCase
{
    private InspectableArrayCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new InspectableArrayCache();
    }

    /** @return iterable<int, array<int, callable>> */
    // phpcs:disable
    public static function provideCookieLines(): iterable
    {
        yield [
            static fn(): array => [
                [
                    10,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                    true,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                    'secure',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                    false,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                    true,
                    true,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                    'secure',
                    'httponly',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                    false,
                    false,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                ],
            ],
        ];

        yield [
            static fn(): array => [
                [
                    10,
                    '/example/',
                    'www.example.com',
                    false,
                    true,
                ],
                [
                    'expires=' . gmdate('D, d M Y H:i:s T', time() + 10),
                    'path=/example/',
                    'domain=www.example.com',
                    'httponly',
                ],
            ],
        ];
    }

    /**
     * @return iterable<int, array<int, (callable(): array{0: int, 1: string})>>
     */
    public static function provideHeaderExpiresCombos(): iterable
    {
        yield [
            static fn(): array => [
                0,
                '',
            ],
        ];

        yield [
            static function (): array {
                $t = 1;

                return [
                    $t,
                    sprintf(
                        '; expires=%s',
                        gmdate('D, d M Y H:i:s T', time() + $t)
                    ),
                ];
            },
        ];

        yield [
            static function (): array {
                $t = 60 * 5;

                return [
                    $t,
                    sprintf(
                        '; expires=%s',
                        gmdate('D, d M Y H:i:s T', time() + $t)
                    ),
                ];
            },
        ];

        yield [
            static function (): array {
                $t = 60 * 60 * 24 * 31;

                return [
                    $t,
                    sprintf(
                        '; expires=%s',
                        gmdate('D, d M Y H:i:s T', time() + $t)
                    ),
                ];
            },
        ];
    }
    // phpcs:enable

    /** @param (callable(): array{array<int, mixed>, array<int, string>}) $setup */
    #[DataProvider('provideCookieLines')]
    #[Test]
    public function setCookieLine(callable $setup): void
    {
        self::waitUntilTheNextSecond();

        [$cookieParams, $cookieLineChunks] = $setup();

        $next = static function (ServerRequestInterface $request): ResponseInterface {
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);
            assert($session instanceof Session);
            $session->begin();

            return new Response();
        };

        $middleware = new SessionMiddleware('Elmo', $this->cache, $cookieParams);

        $response = await($middleware(new ServerRequest('GET', 'https://www.example.com'), $next));

        $cookieChunks = explode('; ', $response->getHeaderLine('Set-Cookie'));
        array_shift($cookieChunks);

        self::assertSame($cookieLineChunks, $cookieChunks);
    }

    /** @param callable(): array{0: int, 1: string} $cookieMonster */
    #[DataProvider('provideHeaderExpiresCombos')]
    #[Test]
    public function cookiesExpiresBasedOnConfiguration(callable $cookieMonster): void
    {
        self::waitUntilTheNextSecond();

        [$expires, $cookieHeaderSuffix] = $cookieMonster();

        $cookieName  = 'CookieMonster';
        $cookieValue = 'cookies';
        $middleware  = new SessionMiddleware($cookieName, $this->cache, [$expires]);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        )->withCookieParams([$cookieName => $cookieValue]);

        $next = (static fn (): ResponseInterface => new Response());

        $response = await($middleware($request, $next));

        self::assertSame(
            [
                'Set-Cookie' => [
                    $cookieName . '=' . $cookieValue . $cookieHeaderSuffix,
                ],
            ],
            $response->getHeaders(),
        );
    }

    #[Test]
    public function sessionDoesntExistsAndNotStartingOne(): void
    {
        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        );
        $next    = static function (ServerRequestInterface $request) use (&$session): ResponseInterface {
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $middleware($request, $next);

        self::assertInstanceOf(Session::class, $session);
        self::assertCount(0, $this->cache->getData());
        self::assertFalse($session->isActive());
    }

    #[Test]
    public function sessionDoesntExistsAndStartingOne(): void
    {
        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        );
        $session = null;
        $next    = static function (ServerRequestInterface $request) use (&$session): ResponseInterface {
            self::assertInstanceOf(Session::class, $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME));
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->setContents(['foo' => 'bar']);
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $middleware($request, $next);

        self::assertInstanceOf(Session::class, $session);
        self::assertCount(1, $this->cache->getData());
        self::assertTrue($session->isActive());
        self::assertSame([
            $session->getId() => ['foo' => 'bar'],
        ], $this->cache->getData());
    }

    #[Test]
    public function sessionExistsAndKeepingItAlive(): void
    {
        $contents   = ['type' => 'Sand'];
        $cookieName = 'CookieMonster';
        $this->cache->set('cookies', ['Chocolate Chip']);
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        )->withCookieParams([$cookieName => 'cookies']);

        $session = null;
        $next    = static function (ServerRequestInterface $request) use ($contents, &$session): ResponseInterface {
            self::assertInstanceOf(Session::class, $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME));
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->setContents($contents);
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $response = await($middleware($request, $next));

        $sandCoookies = await($this->cache->get('cookies'));

        self::assertInstanceOf(Session::class, $session);
        self::assertCount(1, $this->cache->getData());
        self::assertTrue($session->isActive());
        self::assertSame($contents, $sandCoookies);
        self::assertSame($cookieName . '=cookies', $response->getHeaderLine('Set-Cookie'));
    }

    #[Test]
    public function sessionExistsAndEndingIt(): void
    {
        $cookieName = 'CookieMonster';
        $cache      = new ArrayCache();
        $cache->set('cookies', ['Chocolate Chip']);
        $middleware = new SessionMiddleware($cookieName, $this->cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        )->withCookieParams([$cookieName => 'cookies']);

        $session = null;
        $next    = static function (ServerRequestInterface $request) use (&$session): ResponseInterface {
            self::assertInstanceOf(Session::class, $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME));
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->end();
            $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

            return new Response();
        };

        $response = await($middleware($request, $next));

        self::assertInstanceOf(Session::class, $session);
        self::assertCount(0, $this->cache->getData());
        self::assertFalse($session->isActive());
        self::assertSame($cookieName . '=deleted; expires=Thu, 01 Jan 1970 00:00:01 GMT', $response->getHeaderLine('Set-Cookie'));
    }

    #[Test]
    public function updateCacheDeletesOldIds(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->never();
        $cache->shouldReceive('set')->once();
        $cache->shouldReceive('delete')->twice()->andReturn(resolve(true));

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        );

        $next = static function (ServerRequestInterface $request): ResponseInterface {
            self::assertInstanceOf(Session::class, $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME));
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->regenerate();
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->regenerate();

            return new Response();
        };

        await($middleware($request, $next));
    }

    #[Test]
    public function aSessionIdIsAlwaysCheckedForInTheCache(): void
    {
        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('cookies')->once();
        $cache->shouldReceive('set')->once();

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        )->withCookieParams([$cookieName => 'cookies']);

        $next = (static fn (): ResponseInterface => new Response());

        await($middleware($request, $next));
    }

    #[Test]
    public function anErrorFromTheCacheShouldBubbleUp(): void
    {
        self::expectException(Throwable::class);
        self::expectExceptionMessage('Error on the cache layer');

        $cache = Mockery::mock(CacheInterface::class);
        $cache->shouldReceive('get')->with('cookies')->once()->andReturn(reject(new Exception('Error on the cache layer')));

        $cookieName = 'CookieMonster';
        $middleware = new SessionMiddleware($cookieName, $cache);

        $request = new ServerRequest(
            'GET',
            'https://www.example.com/',
        )->withCookieParams([$cookieName => 'cookies']);

        $next = (static fn (): ResponseInterface => new Response());

        await($middleware($request, $next));
    }
}
