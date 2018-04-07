<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

final class Session
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var array
     */
    private $contents;

    /**
     * @var SessionIdInterface
     */
    private $sessionId;

    /**
     * @var string[]
     */
    private $oldIds = [];

    /**
     * @var int
     */
    private $status = \PHP_SESSION_NONE;

    /**
     * @param string             $id
     * @param array              $contents
     * @param SessionIdInterface $sessionId
     */
    public function __construct(string $id, array $contents, SessionIdInterface $sessionId)
    {
        $this->id = $id;
        $this->contents = $contents;
        $this->sessionId = $sessionId;

        if ($this->id !== '') {
            $this->status = PHP_SESSION_ACTIVE;
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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

    /**
     * @return string[]
     */
    public function getOldIds(): array
    {
        return $this->oldIds;
    }

    public function begin()
    {
        if ($this->status === \PHP_SESSION_ACTIVE) {
            return true;
        }

        $this->status = \PHP_SESSION_ACTIVE;

        if ($this->id === '') {
            $this->id = $this->sessionId->generate();
        }
    }

    public function end()
    {
        if ($this->status === \PHP_SESSION_NONE) {
            return true;
        }

        $this->oldIds[] = $this->id;
        $this->status = \PHP_SESSION_NONE;
        $this->id = '';
        $this->contents = [];
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === \PHP_SESSION_ACTIVE;
    }

    public function regenerate(): bool
    {
        // Can only regenerate active sessions
        if ($this->status !== \PHP_SESSION_ACTIVE) {
            return false;
        }

        $this->oldIds[] = $this->id;
        $this->id = $this->sessionId->generate();

        return true;
    }
}
