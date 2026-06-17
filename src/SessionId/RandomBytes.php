<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware\SessionId;

use WyriHaximus\React\Http\Middleware\SessionIdInterface;

use function bin2hex;
use function random_bytes;

final readonly class RandomBytes implements SessionIdInterface
{
    public const int DEFAULT_LENGTH = 32;

    /** @param int<1, max> $length */
    public function __construct(private int $length = self::DEFAULT_LENGTH)
    {
    }

    public function generate(): string
    {
        return bin2hex(random_bytes($this->length));
    }
}
