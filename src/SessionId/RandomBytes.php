<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware\SessionId;

use WyriHaximus\React\Http\Middleware\SessionIdInterface;

final class RandomBytes implements SessionIdInterface
{
    const DEFAULT_LENGTH = 32;

    /**
     * @var int
     */
    private $length = self::DEFAULT_LENGTH;

    /**
     * @param int $length
     */
    public function __construct(int $length = self::DEFAULT_LENGTH)
    {
        $this->length = $length;
    }

    public function generate(): string
    {
        return \bin2hex(\random_bytes($this->length));
    }
}
