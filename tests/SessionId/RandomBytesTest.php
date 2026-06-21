<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware\SessionId;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

use function hex2bin;
use function range;
use function strlen;

final class RandomBytesTest extends AsyncTestCase
{
    /** @return iterable<array{int<1, max>}> */
    public static function provideSizes(): iterable
    {
        yield [RandomBytes::DEFAULT_LENGTH];

        foreach (range(1, 64) as $size) {
            yield [$size];
        }
    }

    /** @param int<1, max> $size */
    #[DataProvider('provideSizes')]
    #[Test]
    public function generate(int $size): void
    {
        $randomBytes = new RandomBytes($size);
        for ($i = 0; $i < 15; $i++) {
            $id = $randomBytes->generate();
            $id = hex2bin($id);
            self::assertIsString($id);
            self::assertSame($size, strlen($id));
        }
    }
}
