<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use InvalidArgumentException;

use function array_key_exists;
use function is_int;
use function is_string;

use const PHP_SESSION_ACTIVE;
use const PHP_SESSION_NONE;

/** @api */
final class Session
{
    /** @var string[] */
    private array $oldIds = [];

    private int $status;

    /** @param array<string, mixed> $contents */
    public function __construct(private string $id, private array $contents, private SessionIdInterface $sessionId)
    {
        $this->status = $this->id === '' ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** @param array<string, mixed> $contents */
    public function setContents(array $contents): void
    {
        $this->contents = $contents;
    }

    /** @return array<string, mixed> */
    public function getContents(): array
    {
        return $this->contents;
    }

    /** @return string[] */
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

    /** @return array<string, mixed> */
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
        if ($clone) {
            $self = clone $this;
        }

        $id     = $session['id'];
        $status = $session['status'];
        if (! is_string($id) || ! is_int($status)) {
            throw new InvalidArgumentException('Session array "id" must be a string and "status" must be an integer.');
        }

        /** @var array<string, mixed> $contents */
        $contents = $session['contents'];
        /** @var array<string> $oldIds */
        $oldIds = $session['oldIds'];

        $self->id       = $id;
        $self->contents = $contents;
        $self->oldIds   = $oldIds;
        $self->status   = $status;

        return $self;
    }
}
