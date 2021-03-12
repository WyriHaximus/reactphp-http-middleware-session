<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware\SessionId;

use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

use function range;
use function Safe\hex2bin;
use function strlen;

/**
 * @internal
 */
final class RandomBytesTest extends AsyncTestCase
{
    /**
     * @return iterable<array<int>>
     */
    public function provideSizes(): iterable
    {
        yield [RandomBytes::DEFAULT_LENGTH];

        foreach (range(1, 64) as $size) {
            yield [$size];
        }
    }

    /**
     * @dataProvider provideSizes
     */
    public function testGenerate(int $size): void
    {
        $randomBytes = new RandomBytes($size);
        for ($i = 0; $i < 15; $i++) {
            $id = $randomBytes->generate();
            $id = hex2bin($id);
            self::assertSame($size, strlen($id));
        }
    }
}
