<?php

namespace LaravelPdoOdbc\Flavours\Snowflake;

use Closure;
use Exception;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use LaravelPdoOdbc\Flavours\Snowflake\PDO\Statement;
use LaravelPdoOdbc\ODBCConnector;
use PDO;

/**
 * Snowflake Connector
 * Inspiration: https://github.com/jenssegers/laravel-mongodb.
 */
class Connector extends ODBCConnector implements OdbcDriver
{
    /**
     * Establish a database connection.
     *
     * @return PDO
     *
     * @internal param array $options
     */
    public function connect(array $config)
    {
        $connection = null;
        $usingSnowflakeDriver = $config['driver'] === 'snowflake_native';

        // the PDO Snowflake driver was installed and the driver was snowflake, start using snowflake driver.
        if ($usingSnowflakeDriver) {
            $this->dsnPrefix = 'snowflake';
            $this->dsnIncludeDriver = false;

            if (! extension_loaded('pdo_snowflake')) {
                throw new Exception('Native Snowflake driver pdo_snowflake was not enabled');
            }
        }

        $connection = parent::connect($config);

        if ($usingSnowflakeDriver === false) {
            // custom Statement class to resolve Streaming value and parameters.
            $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class, [$connection]]);
        }

        return $connection;
    }

    /**
     * Register the connection driver into the DatabaseManager.
     */
    public static function registerDriver(): Closure
    {
        return function ($connection, $database, $prefix, $config) {
            $connection = (new self())->connect($config);

            // create connection
            $db = new Connection($connection, $database, $prefix, $config);

            // set default fetch mode for PDO
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $db->getFetchMode());

            return $db;
        };
    }
}
