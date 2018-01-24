<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

interface SessionIdInterface
{
    public function generate(): string;
}
