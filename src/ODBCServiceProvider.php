<?php

namespace LaravelPdoOdbc;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;

class ODBCServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        Connection::resolverFor('odbc', ODBCConnector::registerDriver());
        Connection::resolverFor('snowflake', Flavours\Snowflake\Connector::registerDriver());
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }
}
