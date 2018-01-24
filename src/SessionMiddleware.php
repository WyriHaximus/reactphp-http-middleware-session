<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use HansOtt\PSR7Cookies\RequestCookies;
use HansOtt\PSR7Cookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Cache\CacheInterface;
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
        $id = $this->getId($request);

        return $this->cache->get($id)->otherwise(function () {
            return resolve([]);
        })->then(function ($sessionData) use ($next, $request, $id) {
            $session = new Session($sessionData);
            $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

            return resolve($next($request))->then(function (ResponseInterface $response) use ($id, $session) {
                $this->cache->set($id, $session->getContents());
                $cookie = new SetCookie($this->cookieName, $id, ...$this->cookieParams);
                $response = $cookie->addToResponse($response);

                return $response;
            });
        });
    }

    private function getId(ServerRequestInterface $request): string
    {
        $cookies = RequestCookies::createFromRequest($request);

        try {
            if ($cookies->has($this->cookieName)) {
                return $cookies->get($this->cookieName)->getValue();
            }
        } catch (Throwable $et) {
            // Do nothing, only a not found will be thrown so generating our own id now
        }

        return $this->sessionId->generate();
    }
}
