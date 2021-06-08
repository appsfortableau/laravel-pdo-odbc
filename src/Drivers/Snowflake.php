<?php

namespace LaravelPdoOdbc\Drivers;

use PDO;
use LaravelPdoOdbc\ODBCConnector;
use LaravelPdoOdbc\PDO\CustomStatement;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use LaravelPdoOdbc\Drivers\Snowflake\Connection as SnowflakeConnection;

/**
 * Snowflake Connector
 * Inspiration: https://github.com/jenssegers/laravel-mongodb.
 */
class Snowflake extends ODBCConnector implements OdbcDriver
{
    public static function registerDriver()
    {
        return function ($config) {
            $config['database'] = $config['database'] ?? null;

            $pdo = (new self())->connect($config);
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CustomStatement::class, [$pdo]]);
            $connection = new SnowflakeConnection($pdo, $config['database'], isset($config['prefix']) ? $config['prefix'] : '', $config);

            // set default fetch mode for PDO
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $connection->getFetchMode());

            return $connection;
        };
    }
}
