<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

interface SessionIdInterface
{
    /**
     * Generate a random string to be used as sessions ID.
     */
    public function generate(): string;
}
