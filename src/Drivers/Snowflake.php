<?php

namespace LaravelPdoOdbc\Drivers;

use LaravelPdoOdbc\ODBCConnector;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use LaravelPdoOdbc\Drivers\Snowflake\Connection as SnowflakeConnection;

/**
 * Snowflake Connector
 * Inspiration: https://github.com/jenssegers/laravel-mongodb
 */
class Snowflake extends ODBCConnector implements OdbcDriver
{
    public static function registerDriver()
    {
        return function ($config, $name) {
            $config['database'] = $config['database'] ?? null;

            $pdoConnection = (new self())->connect($config);
            $connection = new SnowflakeConnection($pdoConnection, $config['database'], isset($config['prefix']) ? $config['prefix'] : '', $config);

            return $connection;
        };
    }
}
