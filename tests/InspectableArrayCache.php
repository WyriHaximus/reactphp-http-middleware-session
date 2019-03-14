<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class InspectableArrayCache implements CacheInterface
{
    /** @var array */
    private $data = [];

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param  string           $key
     * @param  mixed|null       $default
     * @return PromiseInterface
     */
    public function get($key, $default = null): PromiseInterface
    {
        if (!isset($this->data[$key])) {
            return resolve($default);
        }

        return resolve($this->data[$key]);
    }

    /**
     * @param  string           $key
     * @param  mixed            $value
     * @param  ?float           $ttl
     * @return PromiseInterface
     */
    public function set($key, $value, $ttl = null): PromiseInterface
    {
        $this->data[$key] = $value;

        return resolve(true);
    }

    /**
     * @param  string           $key
     * @return PromiseInterface
     */
    public function delete($key): PromiseInterface
    {
        unset($this->data[$key]);

        return resolve(true);
    }
}
