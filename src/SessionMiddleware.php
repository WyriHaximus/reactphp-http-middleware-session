<?php declare(strict_types=1);

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

final class SessionMiddleware
{
    const ATTRIBUTE_NAME = 'wyrihaximus.react.http.middleware.session';

    const DEFAULT_COOKIE_PARAMS = [
        0,
        '',
        '',
        false,
        false,
    ];

    /**
     * @var string
     */
    private $cookieName;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var array
     */
    private $cookieParams;

    /**
     * @var SessionIdInterface
     */
    private $sessionId;

    /**
     * @param string                  $cookieName
     * @param CacheInterface          $cache
     * @param array                   $cookieParams
     * @param SessionIdInterface|null $sessionId
     */
    public function __construct(
        string $cookieName,
        CacheInterface $cache,
        array $cookieParams = [],
        SessionIdInterface $sessionId = null
    ) {
        $this->cookieName = $cookieName;
        $this->cache = $cache;
        $this->cookieParams = array_replace(self::DEFAULT_COOKIE_PARAMS, $cookieParams);

        if ($sessionId === null) {
            $sessionId = new RandomBytes();
        }
        $this->sessionId = $sessionId;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        return $this->fetchSessionFromRequest($request)->then(function (Session $session) use ($next, $request) {
            $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

            return resolve(
                $next($request)
            )->then(function (ResponseInterface $response) use ($session) {
                return $this->updateCache($session)->then(function () use ($response) {
                    return $response;
                });
            })->then(function ($response) use ($session) {
                $cookie = $this->getCookie($session);
                $response = $cookie->addToResponse($response);

                return $response;
            });
        });
    }

    private function fetchSessionFromRequest(ServerRequestInterface $request): PromiseInterface
    {
        $id = '';
        $cookies = RequestCookies::createFromRequest($request);

        try {
            if (!$cookies->has($this->cookieName)) {
                return resolve(new Session($id, [], $this->sessionId));
            }
            $id = $cookies->get($this->cookieName)->getValue();

            return $this->fetchSessionDataFromCache($id)->then(function (array $sessionData) use ($id) {
                return new Session($id, $sessionData, $this->sessionId);
            });
        } catch (Throwable $et) {
            // Do nothing, only a not found will be thrown so generating our own id now
        }

        return resolve(new Session($id, [], $this->sessionId));
    }

    private function fetchSessionDataFromCache(string $id): PromiseInterface
    {
        if ($id === '') {
            return resolve([]);
        }

        return $this->cache->get($id)->then(function ($result) {
            if ($result === null) {
                return resolve([]);
            }

            return $result;
        }, function () {
            return resolve([]);
        });
    }

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
