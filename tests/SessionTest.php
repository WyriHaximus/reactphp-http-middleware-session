<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use WyriHaximus\React\Http\Middleware\Session;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

/**
 * @internal
 */
final class SessionTest extends TestCase
{
    public function testId(): void
    {
        $session = new Session('id', [], new RandomBytes());
        self::assertSame('id', $session->getId());
    }

    public function testData(): void
    {
        $dataFirst = [
            'a' => 'b',
        ];

        $dataSecond = [
            'b' => 'a',
        ];

        $session = new Session('id', $dataFirst, new RandomBytes());
        self::assertSame($dataFirst, $session->getContents());
        $session->setContents($dataSecond);
        self::assertSame($dataSecond, $session->getContents());
    }

    public function testState(): void
    {
        $session = new Session('', [], new RandomBytes());
        self::assertFalse($session->isActive());
        self::assertSame('', $session->getId());
        self::assertSame([], $session->getOldIds());
        self::assertSame([], $session->getContents());

        $session->begin();
        $id = $session->getId();
        self::assertTrue($session->isActive());
        self::assertTrue(\strlen($id) >= 1);
        self::assertSame([], $session->getOldIds());
        self::assertSame([], $session->getContents());

        $session->setContents(['foo' => 'bar']);
        self::assertTrue($session->isActive());
        self::assertTrue(\strlen($session->getId()) >= 1);
        self::assertSame($id, $session->getId());
        self::assertSame([], $session->getOldIds());
        self::assertSame(['foo' => 'bar'], $session->getContents());

        $session->regenerate();
        self::assertTrue($session->isActive());
        self::assertTrue(\strlen($session->getId()) >= 1);
        self::assertNotSame($id, $session->getId());
        self::assertSame([
            $id,
        ], $session->getOldIds());
        self::assertSame(['foo' => 'bar'], $session->getContents());

        $firstId = $id;
        $id = $session->getId();
        $session->end();
        self::assertFalse($session->isActive());
        self::assertTrue(\strlen($session->getId()) == 0);
        self::assertNotSame($id, $session->getId());
        self::assertSame('', $session->getId());
        self::assertSame([
            $firstId,
            $id,
        ], $session->getOldIds());
        self::assertSame([], $session->getContents());
    }

    public function testToFromArray(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame(
            [
                'id' => '',
                'contents' => [],
                'oldIds' => [],
                'status' => 1,
            ],
            $session->toArray()
        );

        $session->begin();

        self::assertSame(
            [
                'id' => $session->getId(),
                'contents' => [],
                'oldIds' => [],
                'status' => 2,
            ],
            $session->toArray()
        );

        $array = $session->toArray();
        $newSession = $session->fromArray($array);
        self::assertNotSame($session, $newSession);
        self::assertSame($array, $newSession->toArray());
    }

    public function testToFromArrayNoClone(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame(
            [
                'id' => '',
                'contents' => [],
                'oldIds' => [],
                'status' => 1,
            ],
            $session->toArray()
        );

        $session->begin();

        self::assertSame(
            [
                'id' => $session->getId(),
                'contents' => [],
                'oldIds' => [],
                'status' => 2,
            ],
            $session->toArray()
        );

        $array = $session->toArray();
        $newSession = $session->fromArray($array, false);
        self::assertSame($session, $newSession);
        self::assertSame($array, $newSession->toArray());
    }

    public function provideSessionArrayWithMissingItems()
    {
        yield [
            [
                'contents' => \bin2hex(\random_bytes(3)),
                'oldIds' => \bin2hex(\random_bytes(3)),
                'status' => \bin2hex(\random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => \bin2hex(\random_bytes(3)),
                'oldIds' => \bin2hex(\random_bytes(3)),
                'status' => \bin2hex(\random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => \bin2hex(\random_bytes(3)),
                'contents' => \bin2hex(\random_bytes(3)),
                'status' => \bin2hex(\random_bytes(3)),
            ],
        ];

        yield [
            [
                'id' => \bin2hex(\random_bytes(3)),
                'contents' => \bin2hex(\random_bytes(3)),
                'oldIds' => \bin2hex(\random_bytes(3)),
            ],
        ];
    }

    /**
     * @dataProvider provideSessionArrayWithMissingItems
     */
    public function testFromArrayThrowsOnMissingElements(array $session): void
    {
        self::expectException(\InvalidArgumentException::class);
        (new Session('', [], new RandomBytes()))->fromArray($session);
    }

    public function testRegenerateShouldOnlyRegenerateWhenSessionIsActive(): void
    {
        $session = new Session('', [], new RandomBytes());

        self::assertSame('', $session->getId());
        self::assertSame(false, $session->isActive());

        self::assertFalse($session->regenerate());

        self::assertSame('', $session->getId());
        self::assertSame(false, $session->isActive());

        $session->begin();

        $sid = $session->getId();
        self::assertGreaterThan(0, \strlen($sid));
        self::assertSame(true, $session->isActive());

        self::assertTrue($session->regenerate());

        self::assertGreaterThan(0, \strlen($session->getId()));
        self::assertNotSame($sid, $session->getId());
        self::assertSame(true, $session->isActive());
    }
}
