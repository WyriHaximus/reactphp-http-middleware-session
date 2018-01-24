<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware\SessionId;

use ApiClients\Tools\TestUtilities\TestCase;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

final class RandomBytesTest extends TestCase
{
    public function provideSizes()
    {
        yield [RandomBytes::DEFAULT_LENGTH];
        foreach (range(1, 1024) as $size) {
            yield [$size];
        }
    }

    /**
     * @dataProvider provideSizes
     * @param int $size
     */
    public function testGenerate(int $size = null)
    {
        $randomBytes = new RandomBytes($size);
        for ($i = 0; $i < 15; $i++) {
            $id = $randomBytes->generate();
            $id = hex2bin($id);
            self::assertSame($size, strlen($id));
        }
    }
}
