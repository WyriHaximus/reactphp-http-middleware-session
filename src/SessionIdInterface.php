<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

interface SessionIdInterface
{
    /**
     * Generate a random string to be used as sessions ID.
     *
     * @return string
     */
    public function generate(): string;
}
