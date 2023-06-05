# ODBC/Snowflake Integration for Laravel Framework

This repository provides seamless integration of ODBC/Snowflake with Laravel Eloquent.
It aims to create a comprehensive ODBC package for Laravel, while also
functioning as a standalone solution.

Unlike the `odbc_*` functions, this package utilizes the `PDO` class,
resulting in smoother and more convenient integration with Eloquent.

The primary goal of this package is to offer a standardized approach to connect
with an ODBC connection. It supports custom grammars and schemas to accommodate
various ODBC connections, such as Snowflake.

## How to Install

Before proceeding, ensure that you have PHP version 8.x installed on your system.

To add the package to your project, run the following command:

```bash
composer require yoramdelangen/laravel-pdo-odbc
```

By default, the package will be automatically registered through the
`package:discover` command.

Alternatively, you can manually register the service provider in the `app.php` file:

```php
'providers' => [
  // ...
  LaravelPdoOdbc\ODBCServiceProvider::class,
];
```

If you intend to use the `snowflake_pdo` PHP extension, please follow the
installation guide provided [here](https://github.com/snowflakedb/pdo_snowflake/)
to set it up.

Starting from version `1.2.0`, the package includes support for `snowflake_pdo`,
but it will still function without the Snowflake extension (via ODBC).

## Configuration

The available driver flavors are:

- ODBC (generic)
- Snowflake (via ODBC and native through PHP extension)
- ...

### Snowflake Specific environment variables

You have the option to customize the Snowflake driver using the following parameters:

```ini
# When set to `false`, column names are automatically uppercased.
SNOWFLAKE_COLUMNS_CASE_SENSITIVE=false

# When set to `true`, column names are wrapped in double quotes and their
# case is determined by the input.
SNOWFLAKE_COLUMNS_CASE_SENSITIVE=true
```

## Usage

Configuring the package is straightforward:

**Add a Database Configuration to `database.php`**

Starting from version 1.2, we recommend using the native Snowflake extension
instead of ODBC, but we'll keep supporting it.

```php
'snowflake_pdo' => [
    'driver' => 'snowflake_native',
    'account' => '{account_name}.eu-west-1',
    'username' => '{username}',
    'password' => '{password}',
    'database' => '{database}',
    'warehouse' => '{warehouse}',
    'schema' => 'PUBLIC', // change it if necessary.
    'options' => [
        // Required for Snowflake usage
        \PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => \PDO::ODBC_SQL_USE_DRIVER
    ]
],
```

You have multiple ways to configure the ODBC connection:

1. Simple configuration using DSN only:

   ```php
   'odbc-connection-name' => [
       'driver' => 'odbc',
       'dsn' => 'OdbcConnectionName', // odbc: will be prefixed
       'username' => 'username',
       'password' => 'password'
   ]
   ```

   or, if you don't have a datasource configured within your ODBC Manager:

   ```php
   'odbc-connection-name' => [
       'driver' => 'odbc',
       'dsn' => 'Driver={Your Snowflake Driver};Server=snowflake.example.com;Port=443;Database={DatabaseName}',
       'username' => 'username',
       'password' => 'password'
   ]
   ```

   > Note: The DSN `Driver` parameter can either be an absolute path to your
   > driver file or the name registered within the `odbcinst.ini` file/ODBC manager.

2. Dynamic configuration:

   ```php
   'odbc-connection-name' => [
       'driver' => 'snowflake',
       // please change this path accordingly your exact location
       'odbc_driver' => '/opt/snowflake/snowflakeodbc/lib/universal/libSnowflake.dylib',
       // 'odbc_driver' => 'Snowflake path Driver',
       'server' => 'host.example.com',
       'username' => 'username',
       'password' => 'password',
       'warehouse' => 'warehouse name',
       'schema' => 'PUBLIC', // most ODBC connections use the default value
   ]
   ```

   > All fields, except for `driver`, `odbc_driver`, `options`, `username`, and
   > `password`, will be dynamically added to the DSN connection string.
   >
   > Note: The DSN `odbc_driver` parameter can either be an absolute path to
   > your driver file or the name registered within the `odbcinst.ini`
   > file/ODBC manager.

## Eloquent ORM

You can use Laravel, Eloquent ORM, and other Illuminate components as usual.

```php
# Facade
$books = DB::connection('odbc-connection-name')
            ->table('books')
            ->where('Author', 'Abram Andrea')
            ->get();

# ORM
$books = Book::where('Author', 'Abram Andrea')->get();
```

## Troubleshooting and more info

We have documented all weird behavious we encountered with the ODBC driver for
Snowflake. In case of trouble of weird messages, checkout the following links:

- [Snowflake ODBC](docs/snowflake-odbc.md)
- [Snowflake ODBC Troubleshooting](docs/snowflake-odbc-troubleshooting.md)

## Customization

- [Custom `getLastInsertId()` Function](docs/custom-last-insert-id.md)
- [Custom Processor/QueryGrammar/SchemaGrammar](docs/custom-grammers.md)
