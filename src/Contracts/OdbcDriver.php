<?php

namespace LaravelPdoOdbc\Contracts;

use Closure;

interface OdbcDriver
{
    public static function registerDriver(): Closure;
}
