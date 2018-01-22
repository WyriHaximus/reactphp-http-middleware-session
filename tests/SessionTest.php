<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use WyriHaximus\React\Http\Middleware\Session;

final class SessionTest extends TestCase
{
    public function testData()
    {
        $dataFirst = [
            'a' => 'b',
        ];

        $dataSecond = [
            'b' => 'a',
        ];

        $session = new Session($dataFirst);
        self::assertSame($dataFirst, $session->getContents());
        $session->setContents($dataSecond);
        self::assertSame($dataSecond, $session->getContents());
    }
}
