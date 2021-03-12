# Middleware that takes care of session handling

![Continuous Integration](https://github.com/wyrihaximus/php-http-middleware-session/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/wyrihaximus/http-middleware-session/v/stable.png)](https://packagist.org/packages/wyrihaximus/http-middleware-session)
[![Total Downloads](https://poser.pugx.org/wyrihaximus/http-middleware-session/downloads.png)](https://packagist.org/packages/wyrihaximus/http-middleware-session/stats)
[![Code Coverage](https://coveralls.io/repos/github/WyriHaximus/php-http-middleware-session/badge.svg?branchmaster)](https://coveralls.io/github/WyriHaximus/php-http-middleware-session?branch=master)
[![Type Coverage](https://shepherd.dev/github/WyriHaximus/php-http-middleware-session/coverage.svg)](https://shepherd.dev/github/WyriHaximus/php-http-middleware-session)
[![License](https://poser.pugx.org/wyrihaximus/http-middleware-session/license.png)](https://packagist.org/packages/wyrihaximus/http-middleware-session)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-http-middleware-session
```

This middleware takes care of session handling. It uses [`react/cache`](https://reactphp.org/cache/) for storage or
any cache handler implementing [`React\Cache\CacheInterface`](https://github.com/reactphp/react/wiki/Users#cache-implementations).

# Usage

```php
$server = new Server(
    $loop,
    /** Other Middleware */
    new SessionMiddleware(
        'CookieName',
        $cache, // Instance implementing React\Cache\CacheInterface
        [ // Optional array with cookie settings, order matters
            0, // expiresAt, int, default
            '', // path, string, default
            '', // domain, string, default
            false, // secure, bool, default
            false // httpOnly, bool, default
        ],
    ),
    /** Other Middleware */
    function (ServerRequestInterface $request) {
        $session = $request->getAttribute(SessionMiddleware::ATTRIBUTE_NAME);

        // Overwrite session contents, the details of changing specific keys is up to you
        $session->setContents([
            'foo' => 'bar',
        ]);

        // Get session contents
        var_export($session->getContents()); // Prints something like: ['foo' = 'bar']

        return new Response();
    }
);
```

# Response cache

Using this middleware together with [`wyrihaximus/react-http-middleware-response-cache`](https://github.com/WyriHaximus/reactphp-http-middleware-response-cache) then
please take a look at [`wyrihaximus/react-http-middleware-response-cache-session-cache-configuration`](https://github.com/WyriHaximus/reactphp-http-middleware-response-cache-session-cache-configuration) to
ensure you don't cache responses from users with active sessions.

## To/From array

In case you need to pass a session into a child process it has `toArray` and `fromArray` methods:

```php
$array = $session->toArray();
// Transfer to child process
$session = (new Session('', [], new RandomBytes()))->fromArray($array);
// The same can be done transferring changes back to the parent
```

# License

The MIT License (MIT)

Copyright (c) 2020 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
