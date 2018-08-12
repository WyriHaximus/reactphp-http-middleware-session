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

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'contents' => $this->contents,
            'oldIds' => $this->oldIds,
            'status' => $this->status,
        ];
    }

    /**
     * @param  array                     $session
     * @param  bool                      $clone
     * @throws \InvalidArgumentException
     * @return Session
     */
    public function fromArray(array $session, bool $clone = true): self
    {
        if (!isset($session['id']) || !isset($session['contents']) || !isset($session['oldIds']) || !isset($session['oldIds'])) {
            throw new \InvalidArgumentException('Session array most contain "id", "contents", "oldIds", and "status".');
        }

        $clone = $this;
        if ($clone === true) {
            $clone = clone $this;
        }
        $clone->id = $session['id'];
        $clone->contents = $session['contents'];
        $clone->oldIds = $session['oldIds'];
        $clone->status = $session['status'];

        return $clone;
    }
}
