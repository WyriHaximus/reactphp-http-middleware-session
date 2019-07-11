<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use React\Cache\CacheInterface;
use function React\Promise\all;
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

    /**
     * @param  array            $keys
     * @param  mixed|null       $default
     * @return PromiseInterface
     */
    public function getMultiple(array $keys, $default = null)
    {
        $items = [];
        foreach ($keys as $key) {
            if (isset($this->data[$key])) {
                $items[$key] = $this->data[$key];

                continue;
            }

            $items[$key] = $default;
        }

        return resolve($items);
    }

    /**
     * @param  array            $values
     * @param  float|null       $ttl
     * @return PromiseInterface
     */
    public function setMultiple(array $values, $ttl = null)
    {
        foreach ($values as $key => $value) {
            $this->data[$key] = $value;
        }

        return resolve(true);
    }

    /**
     * @param  array            $keys
     * @return PromiseInterface
     */
    public function deleteMultiple(array $keys)
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->delete($key);
        }

        return all($items);
    }

    /**
     * @return PromiseInterface
     */
    public function clear()
    {
        $this->data = [];

        return resolve(true);
    }

    /**
     * @param  string           $key
     * @return PromiseInterface
     */
    public function has($key)
    {
        return resolve(isset($this->data[$key]));
    }
}
