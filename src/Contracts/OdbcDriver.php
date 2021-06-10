<?php

namespace LaravelPdoOdbc\Contracts;

use Closure;
use LaravelPdoOdbc\ODBCConnector;

interface OdbcDriver
{
    public static function registerDriver(): Closure;
}
