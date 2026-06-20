<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use React\Cache\CacheInterface;
use React\Promise\PromiseInterface;

use function array_key_exists;
use function React\Promise\resolve;

final class InspectableArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @return array<string, mixed> */
    public function getData(): array
    {
        return $this->data;
    }

    /** @param  mixed $default */
    // phpcs:disable
    public function get($key, $default = null): PromiseInterface
    {
        if (! array_key_exists($key, $this->data)) {
            return resolve($default);
        }

        return resolve($this->data[$key]);
    }
    // phpcs:enable

    /**
     * @param  mixed  $value
     * @param  ?float $ttl
     */
    // phpcs:disable
    public function set($key, $value, $ttl = null): PromiseInterface
    {
        $this->data[$key] = $value;

        return resolve(true);
    }
    // phpcs:enable

    // phpcs:disable
    public function delete($key): PromiseInterface
    {
        unset($this->data[$key]);

        return resolve(true);
    }
    // phpcs:enable

    /**
     * @param  array<string> $keys
     * @param  mixed          $default
     *
     * @return  PromiseInterface<array<mixed>>
     */
    // phpcs:disable
    public function getMultiple(array $keys, $default = null): PromiseInterface
    {
        $items = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->data)) {
                $items[$key] = $this->data[$key];

                continue;
            }

            $items[$key] = $default;
        }

        return resolve($items);
    }
    // phpcs:enable

    /** @param  array<mixed> $values */
    // phpcs:disable
    public function setMultiple(array $values, $ttl = null): PromiseInterface
    {
        foreach ($values as $key => $value) {
            $this->data[$key] = $value; // @phpstan-ignore-line
        }

        return resolve(true);
    }
    // phpcs:enable

    /** @param  array<string> $keys */
    public function deleteMultiple(array $keys): PromiseInterface
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return resolve(true);
    }

    public function clear(): PromiseInterface
    {
        $this->data = [];

        return resolve(true);
    }

    // phpcs:disable
    public function has($key): PromiseInterface
    {
        return resolve(array_key_exists($key, $this->data));
    }
    // phpcs:enable
}
