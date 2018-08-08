# Middleware that takes care of session handling

[![Build Status](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-session.svg?branch=master)](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-session)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-http-middleware-session/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-session)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-http-middleware-session/downloads.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-session)
[![Code Coverage](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-http-middleware-session/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-http-middleware-session/?branch=master)
[![License](https://poser.pugx.org/WyriHaximus/react-http-middleware-session/license.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-session)
[![PHP 7 ready](http://php7ready.timesplinter.ch/WyriHaximus/reactphp-http-middleware-clear-body/badge.svg)](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-clear-body)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-http-middleware-session
```

This middleware takes care of session handling. It uses [`react/cache`](https://reactphp.org/cache/) for storage or 
any cache handler implementing [`React\Cache\CacheInterface`](https://github.com/reactphp/react/wiki/Users#cache-implementations).

# Usage

```php
$server = new Server([
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
]);
```

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

Copyright (c) 2018 Cees-Jan Kiewiet

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
