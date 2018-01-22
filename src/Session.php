<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

final class Session
{
    /**
     * @var array
     */
    private $contents = [];

    /**
     * Session constructor.
     * @param array $contents
     */
    public function __construct(array $contents)
    {
        $this->contents = $contents;
    }

    /**
     * @param array $contents
     */
    public function setContents(array $contents)
    {
        $this->contents = $contents;
    }

    /**
     * @return array
     */
    public function getContents(): array
    {
        return $this->contents;
    }
}
