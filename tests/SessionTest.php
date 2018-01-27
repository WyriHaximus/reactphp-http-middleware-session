<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use WyriHaximus\React\Http\Middleware\Session;
use WyriHaximus\React\Http\Middleware\SessionId\RandomBytes;

final class SessionTest extends TestCase
{
    public function testId()
    {
        $session = new Session('id', [], new RandomBytes());
        self::assertSame('id', $session->getId());
    }

    public function testData()
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
}
