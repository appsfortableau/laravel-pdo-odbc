<?php

namespace LaravelPdoOdbc\Flavours\Snowflake;

use PDO;
use LaravelPdoOdbc\ODBCConnector;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use LaravelPdoOdbc\Flavours\Snowflake\PDO\Statement;

/**
 * Snowflake Connector
 * Inspiration: https://github.com/jenssegers/laravel-mongodb.
 */
class Connector extends ODBCConnector implements OdbcDriver
{
    public static function registerDriver()
    {
        return function ($config) {
            $config['database'] = $config['database'] ?? null;

            $pdo = (new self())->connect($config);

            // custom Statement class to resolve Streaming value and parameters.
            $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$pdo]]);

            // create connection
            $connection = new Connection($pdo, $config['database'], $config['prefix'] ?? '', $config);

            // set default fetch mode for PDO
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $connection->getFetchMode());

            return $connection;
        };
    }
}
