<?php

namespace LaravelPdoOdbc\Contracts;

use LaravelPdoOdbc\ODBCConnector;

interface OdbcDriver
{
    public static function registerDriver();
}
