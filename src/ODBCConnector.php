<?php

namespace LaravelPdoOdbc;

use PDO;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelPdoOdbc\Contracts\OdbcDriver;
use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class ODBCConnector extends Connector implements ConnectorInterface, OdbcDriver
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
        $options = $this->getOptions($config);

        $dsn = Arr::get($config, 'dsn');

        if (! Str::contains('odbc:', $dsn)) {
            $dsn = 'odbc:'.$dsn;
        }

        $connection = $this->createConnection($dsn, $config, $options);

        return $connection;
    }

    public static function registerDriver()
    {
        return function ($config) {
            $config['database'] = $config['database'] ?? null;

            $pdoConnection = (new self())->connect($config);
            $connection = new ODBCConnection($pdoConnection, $config['database'], isset($config['prefix']) ? $config['prefix'] : '', $config);

            return $connection;
        };
    }
}
