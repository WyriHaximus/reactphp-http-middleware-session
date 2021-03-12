<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use HansOtt\PSR7Cookies\RequestCookies;
use HansOtt\PSR7Cookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;
use Throwable;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

use function React\Promise\resolve;
use function Safe\array_replace;
use function time;

final class SessionMiddleware
{
    public const ATTRIBUTE_NAME = 'wyrihaximus.react.http.middleware.session';

    public const DEFAULT_COOKIE_PARAMS = [
        0,
        '',
        '',
        false,
        false,
    ];

    private string $cookieName;

    private CacheInterface $cache;

    /** @var array<int, mixed> */
    private array $cookieParams = [];

    private SessionIdInterface $sessionId;

    /**
     * @param array<int, mixed> $cookieParams
     */
    public function __construct(
        string $cookieName,
        CacheInterface $cache,
        array $cookieParams = [],
        ?SessionIdInterface $sessionId = null
    ) {
        $this->cookieName   = $cookieName;
        $this->cache        = $cache;
        $this->cookieParams = array_replace(self::DEFAULT_COOKIE_PARAMS, $cookieParams);

        if ($sessionId === null) {
            $sessionId = new RandomBytes();
        }

        $this->sessionId = $sessionId;
    }

    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        return $this->fetchSessionFromRequest($request)->then(function (Session $session) use ($next, $request): PromiseInterface {
            $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

            return resolve(
                $next($request)
            )->then(
                fn (ResponseInterface $response): PromiseInterface => $this->updateCache($session)->then(static fn (): ResponseInterface => $response)
            )->then(function (ResponseInterface $response) use ($session): ResponseInterface {
                $cookie   = $this->getCookie($session);
                $response = $cookie->addToResponse($response);

                return $response;
            });
        });
    }

    private function fetchSessionFromRequest(ServerRequestInterface $request): PromiseInterface
    {
        $id      = '';
        $cookies = RequestCookies::createFromRequest($request);

        try {
            if (! $cookies->has($this->cookieName)) {
                return resolve(new Session($id, [], $this->sessionId));
            }

            $id = $cookies->get($this->cookieName)->getValue();

            return $this->fetchSessionDataFromCache($id)->then(
                fn (array $sessionData): Session => new Session($id, $sessionData, $this->sessionId)
            );
        } catch (Throwable $et) {
            // Do nothing, only a not found will be thrown so generating our own id now
            // @ignoreException
        }

        return resolve(new Session($id, [], $this->sessionId));
    }

    private function fetchSessionDataFromCache(string $id): PromiseInterface
    {
        if ($id === '') {
            return resolve([]);
        }

        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress TooManyTemplateParams
         */
        return $this->cache->get($id)->then(static function (?array $result): array {
            if ($result === null) {
                return [];
            }

            return $result;
        });
    }

    /**
     * @psalm-suppress TooManyTemplateParams
     */
    private function updateCache(Session $session): PromiseInterface
    {
        foreach ($session->getOldIds() as $oldId) {
            $this->cache->delete($oldId);
        }

        if ($session->isActive()) {
            return resolve($this->cache->set($session->getId(), $session->getContents()));
        }

        return resolve();
    }

    private function getCookie(Session $session): SetCookie
    {
        $cookieParams = $this->cookieParams;

        if ($session->isActive()) {
            // Only set time when expires is set in the future
            if ($cookieParams[0] > 0) {
                $cookieParams[0] += time();
            }

            return new SetCookie($this->cookieName, $session->getId(), ...$cookieParams);
        }

        unset($cookieParams[0]);

        return SetCookie::thatDeletesCookie($this->cookieName, ...$cookieParams);
    }
}
