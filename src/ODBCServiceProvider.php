<?php

namespace LaravelPdoOdbc;

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
        $this->app->resolving('db', function ($db) {
            /* @var DatabaseManager $db */
            $db->extend('odbc', ODBCConnector::registerDriver());
            $db->extend('snowflake', Flavours\Snowflake\Connector::registerDriver());
        });
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
