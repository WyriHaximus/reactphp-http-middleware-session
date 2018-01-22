<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use WyriHaximus\React\Http\Middleware\Session;

final class SessionTest extends TestCase
{
    public function testData()
    {
        $data = [
            'a' => 'b',
        ];

        $session = new Session($data);
        self::assertSame($data, $session->getContents());
    }
}
