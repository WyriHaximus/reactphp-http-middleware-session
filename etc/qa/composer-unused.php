<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;

return static fn (Configuration $config): Configuration => $config
    ->addNamedFilter(\ComposerUnused\ComposerUnused\Configuration\NamedFilter::fromString('react/http'));
