<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

final class Session
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var array<string, mixed>
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
     * @param string               $id
     * @param array<string, mixed> $contents
     * @param SessionIdInterface   $sessionId
     */
    public function __construct(string $id, array $contents, SessionIdInterface $sessionId)
    {
        $this->id = $id;
        $this->contents = $contents;
        $this->sessionId = $sessionId;

        if ($this->id !== '') {
            $this->status = \PHP_SESSION_ACTIVE;
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
     * @param array<string, mixed> $contents
     */
    public function setContents(array $contents): void
    {
        $this->contents = $contents;
    }

    /**
     * @return array<string, mixed>
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

    public function begin(): void
    {
        if ($this->status === \PHP_SESSION_ACTIVE) {
            return;
        }

        $this->status = \PHP_SESSION_ACTIVE;

        if ($this->id === '') {
            $this->id = $this->sessionId->generate();
        }
    }

    public function end(): void
    {
        if ($this->status === \PHP_SESSION_NONE) {
            return;
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

    /**
     * @return array<string, mixed>
     */
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
     * @param  array<string, mixed>      $session
     * @param  bool                      $clone
     * @throws \InvalidArgumentException
     * @return Session
     */
    public function fromArray(array $session, bool $clone = true): self
    {
        if (!\array_key_exists('id', $session) || !\array_key_exists('contents', $session) || !\array_key_exists('oldIds', $session) || !\array_key_exists('status', $session)) {
            throw new \InvalidArgumentException('Session array most contain "id", "contents", "oldIds", and "status".');
        }

        $self = $this;
        if ($clone === true) {
            $self = clone $this;
        }
        $self->id = $session['id'];
        $self->contents = $session['contents'];
        $self->oldIds = $session['oldIds'];
        $self->status = $session['status'];

        return $self;
    }
}
