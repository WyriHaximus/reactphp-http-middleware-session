<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use InvalidArgumentException;

use function array_key_exists;

use const PHP_SESSION_ACTIVE;
use const PHP_SESSION_NONE;

final class Session
{
    private string $id;

    /** @var array<string, mixed> */
    private array $contents;

    private SessionIdInterface $sessionId;

    /** @var string[] */
    private array $oldIds = [];

    private int $status = PHP_SESSION_NONE;

    /**
     * @param array<string, mixed> $contents
     */
    public function __construct(string $id, array $contents, SessionIdInterface $sessionId)
    {
        $this->id        = $id;
        $this->contents  = $contents;
        $this->sessionId = $sessionId;

        if ($this->id === '') {
            return;
        }

        $this->status = PHP_SESSION_ACTIVE;
    }

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
        if ($this->status === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->status = PHP_SESSION_ACTIVE;

        if ($this->id !== '') {
            return;
        }

        $this->id = $this->sessionId->generate();
    }

    public function end(): void
    {
        if ($this->status === PHP_SESSION_NONE) {
            return;
        }

        $this->oldIds[] = $this->id;
        $this->status   = PHP_SESSION_NONE;
        $this->id       = '';
        $this->contents = [];
    }

    public function isActive(): bool
    {
        return $this->status === PHP_SESSION_ACTIVE;
    }

    public function regenerate(): bool
    {
        // Can only regenerate active sessions
        if ($this->status !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $this->oldIds[] = $this->id;
        $this->id       = $this->sessionId->generate();

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
     * @param  array<string, mixed> $session
     *
     * @return Session
     *
     * @throws InvalidArgumentException
     */
    public function fromArray(array $session, bool $clone = true): self
    {
        if (! array_key_exists('id', $session) || ! array_key_exists('contents', $session) || ! array_key_exists('oldIds', $session) || ! array_key_exists('status', $session)) {
            throw new InvalidArgumentException('Session array most contain "id", "contents", "oldIds", and "status".');
        }

        $self = $this;
        if ($clone === true) {
            $self = clone $this;
        }

        $self->id       = $session['id'];
        $self->contents = $session['contents'];
        $self->oldIds   = $session['oldIds'];
        $self->status   = $session['status'];

        return $self;
    }
}
