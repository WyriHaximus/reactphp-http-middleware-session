<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\ServerRequest;
use WyriHaximus\React\Http\Middleware\SessionMiddleware;
use function React\Promise\resolve;

final class SessionMiddlewareTest extends TestCase
{
    /**
     * @var CacheInterface
     */
    private $cache;

    public function setUp()
    {
        parent::setUp();
        $this->cache = new class() implements CacheInterface {
            protected $data = [];

            /**
             * @return array
             */
            public function getData(): array
            {
                return $this->data;
            }

            public function get($key, $default = null)
            {
                if (!isset($this->data[$key])) {
                    return resolve($default);
                }

                return resolve($this->data[$key]);
            }

            public function set($key, $value, $ttl = null)
            {
                $this->data[$key] = $value;
            }

            public function delete($key)
            {
                unset($this->data[$key]);
            }
        };
    }

    public function provideCookieLines()
    {
        yield [
            [
                10,
            ],
            [
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
            ],
        ];

        yield [
            [
                10,
                '/example/',
            ],
            [
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
                'expires=' . gmdate('D, d-M-Y H:i:s T', time() + 10),
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
            $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME)->begin();

            return new Response();
        };

        $middleware = new SessionMiddleware('Elmo', $this->cache, $cookieParams);

        /** @var ResponseInterface $response */
        $response = $this->await($middleware(new ServerRequest('GET', 'https://www.example.com'), $next));

        $cookieChunks = explode('; ', $response->getHeaderLine('Set-Cookie'));
        array_shift($cookieChunks);

        self::assertSame($cookieLineChunks, $cookieChunks);
    }

    public function testSessionDoesntExistsAndNotStartingOne()
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
        self::assertSame(false, $session->isActive());
    }

    public function testSessionDoesntExistsAndStartingOne()
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
        self::assertSame(true, $session->isActive());
        self::assertSame([
            $session->getId() => [
                'foo' => 'bar',
            ],
        ], $this->cache->getData());
    }

    public function testSessionExistsAndKeepingItAlive()
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
        self::assertSame(true, $session->isActive());
        self::assertSame($contents, $sandCoookies);
        self::assertSame($cookieName . '=cookies', $response->getHeaderLine('Set-Cookie'));
    }

    public function testSessionExistsAndEndingIt()
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
        self::assertSame(false, $session->isActive());
        self::assertSame($cookieName . '=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT', $response->getHeaderLine('Set-Cookie'));
    }
}
