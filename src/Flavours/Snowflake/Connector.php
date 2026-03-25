<?php

namespace LaravelPdoOdbc\Flavours\Snowflake;

use Closure;
use Exception;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use LaravelPdoOdbc\ODBCConnector;
use Illuminate\Support\Arr;

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

                // Handle Snowflake key-pair
        if (Arr::get($config, 'authenticator') === 'key_pair') {
            $privateKey = trim((string) Arr::get($config, 'private_key', ''));

            if ($privateKey !== '') {
                $config['priv_key_base64'] = base64_encode($privateKey);
                $config['authenticator'] = 'SNOWFLAKE_JWT';
                $config['odbc_driver'] = $config['odbcdriver'];

                // Force ODBCConnector to build DSN dynamically
                $allowedKeys = ['driver','server','database','warehouse','schema','port','priv_key_pwd','odbc_driver','authenticator','priv_key_base64', 'odbcdriver', 'username', 'options'];
                $config = array_intersect_key($config, array_flip($allowedKeys));
            }
            else{
                throw new Exception('Private key is required for key_pair authentication');
            }
        }

        // the PDO Snowflake driver was installed and the driver was snowflake, start using snowflake driver.
        if ($usingSnowflakeDriver) {
            $this->dsnPrefix = 'snowflake';
            $this->dsnIncludeDriver = false;

            if (!extension_loaded('pdo_snowflake')) {
                throw new Exception('Native Snowflake driver pdo_snowflake was not enabled');
            }
        }

        $connection = parent::connect($config);

        // custom Statement class to resolve Streaming value and parameters.
        $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [\LaravelPdoOdbc\Flavours\Snowflake\PDO\Statement::class, [$connection]]);

        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            if (!env('SNOWFLAKE_DISABLE_FORCE_QUOTED_IDENTIFIER')) {
                $connection->exec('ALTER SESSION SET QUOTED_IDENTIFIERS_IGNORE_CASE = false');
            }

            // set default fetch mode for PDO
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $db->getFetchMode());

            return $db;
        };
    }
}
