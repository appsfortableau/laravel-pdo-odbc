<?php

namespace LaravelPdoOdbc;

use PDO;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class ODBCConnector extends Connector implements ConnectorInterface, OdbcDriver
{
    /**
     * Set dynamically the DSN prefix in case we need it.
     */
    protected string $dsnPrefix = 'odbc';

    /**
     * Include the "Driver=" property in the DSN.
     * In case of Snowflake connection via snowflake_pdo driver we dont want it.
     */
    protected bool $dsnIncludeDriver = true;

    /**
     * Establish a database connection.
     *
     * @return PDO
     *
     * @internal param array $options
     */
    public function connect(array $config)
    {
        $options = $this->getOptions($config);

        // FULL DSN ONLY
        if ($dsn = Arr::get($config, 'dsn')) {
            $dsn = ! Str::contains($this->dsnPrefix.':', $dsn) ? $this->dsnPrefix.':'.$dsn : $dsn;
        }
        // dynamicly build in some way..
        else {
            $dsn = $this->buildDsnDynamicly($config);
        }

        return $this->createConnection($dsn, $config, $options);
    }

    /**
     * Register the connection driver into the DatabaseManager.
     */
    public static function registerDriver(): Closure
    {
        return function ($connection, $database, $prefix, $config) {
            $connection = (new self())->connect($config);
            $connection = new ODBCConnection($connection, $database, $prefix, $config);


            return $connection;
        };
    }

    /**
    * When dynamically building it takes the database configuration key and put it in the DSN.
    */
    protected function buildDsnDynamicly(array $config): string
    {
        // ignore some default props...
        $ignoreProps = ['driver', 'odbc_driver', 'dsn', 'options', 'username', 'password'];
        $props = Arr::except($config, $ignoreProps);

        if ($this->dsnIncludeDriver) {
            $props = ['driver' => Arr::get($config, 'odbc_driver')] + $props;
        }

        // join pieces DSN together
        $props = array_map(function ($val, $key) {
            return $key.'='.$val;
        }, $props, array_keys($props));

        return $this->dsnPrefix.':'.implode(';', $props);
    }
}
