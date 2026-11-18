<?php

require __DIR__.'/../vendor/autoload.php';

if (! function_exists('config')) {
    /** The package reads optional overrides through Laravel's config(); outside an application the defaults apply. */
    function config(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }
}
