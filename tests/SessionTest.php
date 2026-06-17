<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\Session;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

use function bin2hex;
use function random_bytes;
use function strlen;

final class SessionTest extends AsyncTestCase
{
    #[Test]
    public function id(): void
    {
        $session = new Session('id', [], new RandomBytes());
        self::assertSame('id', $session->getId());
    }

    #[Test]
    public function data(): void
    {
        $dataFirst = ['a' => 'b'];

        $dataSecond = ['b' => 'a'];

        $session = new Session('id', $dataFirst, new RandomBytes());
        self::assertSame($dataFirst, $session->getContents());
        $session->setContents($dataSecond);
        self::assertSame($dataSecond, $session->getContents());
    }

    #[Test]
    public function state(): void
    {
        $session = new Session('', [], new RandomBytes());
        self::assertFalse($session->isActive());
        self::assertSame('', $session->getId());
        self::assertSame([], $session->getOldIds());
        self::assertSame([], $session->getContents());

        $session->begin();
        $id = $session->getId();
        self::assertTrue($session->isActive());
        self::assertGreaterThanOrEqual(1, strlen($id));
        self::assertSame([], $session->getOldIds());
        self::assertSame([], $session->getContents());

        $session->setContents(['foo' => 'bar']);
        self::assertTrue($session->isActive());
        self::assertGreaterThanOrEqual(1, strlen($session->getId()));
        self::assertSame($id, $session->getId());
        self::assertSame([], $session->getOldIds());
        self::assertSame(['foo' => 'bar'], $session->getContents());

        $session->regenerate();
        self::assertTrue($session->isActive());
        self::assertGreaterThanOrEqual(1, strlen($session->getId()));
        self::assertNotSame($id, $session->getId());
        self::assertSame([$id], $session->getOldIds());
        self::assertSame(['foo' => 'bar'], $session->getContents());

        $firstId = $id;
        $id      = $session->getId();
        $session->end();
        self::assertFalse($session->isActive());
        self::assertSame(0, strlen($session->getId()));
        self::assertNotSame($id, $session->getId());
        self::assertSame('', $session->getId());
        self::assertSame([
            $firstId,
            $id,
        ], $session->getOldIds());
        self::assertSame([], $session->getContents());
    }

    #[Test]
    public function toFromArray(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame(
            [
                'id' => '',
                'contents' => [],
                'oldIds' => [],
                'status' => 1,
            ],
            $session->toArray(),
        );

        $session->begin();

        self::assertSame(
            [
                'id' => $session->getId(),
                'contents' => [],
                'oldIds' => [],
                'status' => 2,
            ],
            $session->toArray(),
        );

        $newSession = $session->fromArray($session->toArray());
        self::assertNotSame($session, $newSession);
        self::assertSame($session->toArray(), $newSession->toArray());
    }

    #[Test]
    public function toFromArrayNoClone(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame(
            [
                'id' => '',
                'contents' => [],
                'oldIds' => [],
                'status' => 1,
            ],
            $session->toArray(),
        );

        $session->begin();

        self::assertSame(
            [
                'id' => $session->getId(),
                'contents' => [],
                'oldIds' => [],
                'status' => 2,
            ],
            $session->toArray(),
        );

        $newSession = $session->fromArray($session->toArray(), false);
        self::assertSame($session, $newSession);
        self::assertSame($session->toArray(), $newSession->toArray());
    }

    /** @return iterable<int, array<int, array<string, string>>> */
    public static function provideSessionArrayWithMissingItems(): iterable
    {
        yield [
            [
                'contents' => bin2hex(random_bytes(3)),
                'oldIds' => bin2hex(random_bytes(3)),
                'status' => bin2hex(random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => bin2hex(random_bytes(3)),
                'oldIds' => bin2hex(random_bytes(3)),
                'status' => bin2hex(random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => bin2hex(random_bytes(3)),
                'contents' => bin2hex(random_bytes(3)),
                'status' => bin2hex(random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => bin2hex(random_bytes(3)),
                'contents' => bin2hex(random_bytes(3)),
                'oldIds' => bin2hex(random_bytes(3)),
            ],
        ];
    }

    /** @param array<string, string> $session */
    #[DataProvider('provideSessionArrayWithMissingItems')]
    #[Test]
    public function fromArrayThrowsOnMissingElements(array $session): void
    {
        self::expectException(InvalidArgumentException::class);
        new Session('', [], new RandomBytes())->fromArray($session);
    }

    #[Test]
    public function regenerateShouldOnlyRegenerateWhenSessionIsActive(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame('', $session->getId());
        self::assertFalse($session->isActive());

        self::assertFalse($session->regenerate());

        self::assertSame('', $session->getId());
        self::assertFalse($session->isActive());

        $session->begin();

        $sid = $session->getId();
        self::assertGreaterThan(0, strlen($sid));
        self::assertTrue($session->isActive());

        self::assertTrue($session->regenerate());

        self::assertGreaterThan(0, strlen($session->getId()));
        self::assertNotSame($sid, $session->getId());
        self::assertTrue($session->isActive());
    }
}
