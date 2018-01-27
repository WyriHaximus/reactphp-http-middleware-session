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

    /**
     * @var string
     */
    private $cookieName = '';

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var array
     */
    private $cookieParams = [];

    /**
     * @var SessionIdInterface
     */
    private $sessionId;

    /**
     * @param string         $cookieName
     * @param CacheInterface $cache
     * @param array          $cookieParams
     */
    public function __construct(
        string $cookieName,
        CacheInterface $cache,
        array $cookieParams = [],
        SessionIdInterface $sessionId = null
    ) {
        $this->cookieName = $cookieName;
        $this->cache = $cache;
        $this->cookieParams = $cookieParams;

        if ($sessionId === null) {
            $sessionId = new RandomBytes();
        }
        $this->sessionId = $sessionId;
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        return $this->fetchSessionFromRequest($request)->then(function (Session $session) use ($next, $request) {
            $requestSessionId = $session->getId();
            $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

            return resolve(
                $next($request)
            )->then(function (ResponseInterface $response) use ($session, $requestSessionId) {
                $this->updateCache($session->getId(), $requestSessionId, $session->getContents());

                $cookie = new SetCookie($this->cookieName, $session->getId(), ...$this->cookieParams);
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
        return $this->cache->get($id)->otherwise(function () {
            return resolve([]);
        });
    }

    private function updateCache(string $currentSessionId, string $requestSessionId, array $contents)
    {
        if ($currentSessionId !== '') {
            return $this->cache->set($currentSessionId, $contents);
        }

        if ($currentSessionId === '' && $requestSessionId !== '') {
            return $this->cache->remove($requestSessionId);
        }
    }
}
