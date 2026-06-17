<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use HansOtt\PSR7Cookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;
use Throwable;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

use function array_key_exists;
use function array_replace;
use function assert;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function React\Promise\resolve;
use function time;

final readonly class SessionMiddleware
{
    public const string ATTRIBUTE_NAME = 'wyrihaximus.react.http.middleware.session';

    public const array DEFAULT_COOKIE_PARAMS = [
        0,
        '',
        '',
        false,
        false,
    ];

    /** @var array<int, mixed> */
    private array $cookieParams;

    private SessionIdInterface $sessionId;

    /**
     * @param array<int, mixed> $cookieParams
     *
     * @api
     */
    public function __construct(
        private string $cookieName,
        private CacheInterface $cache,
        array $cookieParams = [],
        SessionIdInterface|null $sessionId = null,
    ) {
        $this->cookieParams = array_replace(self::DEFAULT_COOKIE_PARAMS, $cookieParams);

        if (! $sessionId instanceof SessionIdInterface) {
            $sessionId = new RandomBytes();
        }

        $this->sessionId = $sessionId;
    }

    /** @return PromiseInterface<ResponseInterface> */
    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        return $this->fetchSessionFromRequest($request)->then(function (Session $session) use ($next, $request): PromiseInterface {
            $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

            return resolve(
                $next($request),
            )->then(
                function (mixed $response) use ($session): PromiseInterface {
                    assert($response instanceof ResponseInterface);

                    return $this->updateCache($session)->then(static fn (): ResponseInterface => $response);
                },
            )->then(function (ResponseInterface $response) use ($session): ResponseInterface {
                $cookie   = $this->getCookie($session);
                $response = $cookie->addToResponse($response);

                return $response;
            });
        });
    }

    /** @return PromiseInterface<Session> */
    private function fetchSessionFromRequest(ServerRequestInterface $request): PromiseInterface
    {
        $id      = '';
        $cookies = $request->getCookieParams();

        try {
            if (! array_key_exists($this->cookieName, $cookies)) {
                return resolve(new Session($id, [], $this->sessionId));
            }

            $cookieValue = $cookies[$this->cookieName];
            $id          = is_string($cookieValue) ? $cookieValue : '';

            return $this->fetchSessionDataFromCache($id)->then(
                fn (array $sessionData): Session => new Session($id, $sessionData, $this->sessionId),
            );
        } catch (Throwable) {
            // Do nothing, only a not found will be thrown so generating our own id now
            // @ignoreException
        }

        return resolve(new Session($id, [], $this->sessionId));
    }

    /** @return PromiseInterface<array<string, mixed>> */
    private function fetchSessionDataFromCache(string $id): PromiseInterface
    {
        if ($id === '') {
            return resolve([]);
        }

        /** @phpstan-ignore return.type */
        return $this->cache->get($id)->then(static function (mixed $result): array {
            if (! is_array($result)) {
                return [];
            }

            /** @return array<string, mixed> */
            return $result;
        });
    }

    /** @return PromiseInterface<bool|null> */
    private function updateCache(Session $session): PromiseInterface
    {
        foreach ($session->getOldIds() as $oldId) {
            $this->cache->delete($oldId);
        }

        if ($session->isActive()) {
            return resolve($this->cache->set($session->getId(), $session->getContents()));
        }

        return resolve(null);
    }

    private function getCookie(Session $session): SetCookie
    {
        $cookieParams = $this->cookieParams;

        $expires  = $cookieParams[0] ?? 0;
        $path     = $cookieParams[1] ?? '';
        $domain   = $cookieParams[2] ?? '';
        $secure   = $cookieParams[3] ?? false;
        $httpOnly = $cookieParams[4] ?? false;

        assert(is_int($expires));
        assert(is_string($path));
        assert(is_string($domain));
        assert(is_bool($secure));
        assert(is_bool($httpOnly));

        if ($session->isActive()) {
            // Only set time when expires is set in the future
            if ($expires > 0) {
                $expires += time();
            }

            return new SetCookie($this->cookieName, $session->getId(), $expires, $path, $domain, $secure, $httpOnly);
        }

        return SetCookie::thatDeletesCookie($this->cookieName, $path, $domain, $secure, $httpOnly);
    }
}
