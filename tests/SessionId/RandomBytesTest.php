<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware\SessionId;

use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

/**
 * @internal
 */
final class RandomBytesTest extends AsyncTestCase
{
    public function provideSizes(): iterable
    {
        yield [RandomBytes::DEFAULT_LENGTH];
        foreach (\range(1, 64) as $size) {
            yield [$size];
        }
    }

    /**
     * @dataProvider provideSizes
     * @param int $size
     */
    public function testGenerate(int $size): void
    {
        $randomBytes = new RandomBytes($size);
        for ($i = 0; $i < 15; $i++) {
            $id = $randomBytes->generate();
            /** @var string $id */
            $id = \Safe\hex2bin($id);
            self::assertSame($size, \strlen($id));
        }
    }
}
