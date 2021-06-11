# ODBC integration for Laravel Framework

This intergrates almost natively with Laravel Eloquent. Goal is to create a uniform ODBC package for Laravel, but it can run standalone as well. It is a fork of the `abram/laravel-odbc` repository. 

This package does not use the `odbc_*` functions, but the `PDO` class to make the intergration with eloquent much easier and more flawless.

Goal of the package is to provide a generic way of connecting with a ODBC connection. Sometime we need to have a custom grammar(s) and Schema(s) to support a ODBC connection, e.g. like Snowflake.

## # How to install

> `composer require yoramdelangen/laravel-pdo-odbc` To add source in your project.

By default the package will be automaticly registered via the `package:discover` command.

**Manual register service provider in app.php file**

```php
'providers' => [
  ...
  LaravelPdoOdbc\ODBCServiceProvider::class
];
```

## # Configuration

**Snowflake specific configuration**
There is some customization allowed with the Snowflake driver:

```
# when `false` it automaticly uppercases the column names
SNOWFLAKE_COLUMNS_CASE_SENSITIVE=false

# When `true` it wraps the columns in double qoutes and makes them upper/lower case based on the input.
SNOWFLAKE_COLUMNS_CASE_SENSITIVE=true
```

Currently we have the following driver flavours:

- odbc (generic)
- snowflake
- ....

## # Important to know the quirks!

Certain connections can have specific configuration issues we need to resolve before it works properly.

#### Snowflake

Currently there are 2 known issues/quirks for a Snowflake connection:

By default Snowflake ODBC executes `DDL` statements in preparation. Meaning when sending a `$stmt = $pdo->prepare('...');` to Snowflake it will automaticly execute. It's **REQUIRED** to disable this and Snowflake has a ODBC Driver configuration (NOT the DSN config) for it `NoExecuteInSQLPrepare=true`. This option is available since version `2.21.6`.

Must be set in:

- Window: [Non-unix environment](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#setting-parameters-in-windows).
- Unix: [`simba.snowflake.ini` file](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#setting-parameters-in-macos-or-linux).

Read more about it on the following:

- [ODBC parameters](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#configuration-parameters).
- [Changelog June 2020](https://community.snowflake.com/s/article/client-release-history).
- [More about DDL]

> `DDL`: Data Definition Language, commands: ALTER, COMMENT, CREATE, DESCRIBE, DROP, SHOW, USE

Snowflake also doesn't support streaming bind values. Mainly when using `->prepare('..')` statment and following by `$stmt->bindValue(...)` or `$stmt->bindParam()`. We added a CustomStatement class to resolve this issue.

## # Usage Instructions

It's very simple to configure:

**Add database to database.php file**
There are multiple ways to configure the ODBC connection:

Simple via DSN only:

```php
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'OdbcConnectionName', // odbc: will be prefixed
    'username' => 'username',
    'password' => 'password'
]
```

or when you do not have a datasource configured within your ODBC Manager:

```php
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'Driver={Your Snowflake Driver};Server=snowflake.example.com;Port=443;Database={DatabaseName}',
    'username' => 'username',
    'password' => 'password'
]
```

> Note: DSN `Driver` can be a absolute path to your driver file or the name registered within `odbcinst.ini` file/ODBC manager.

Or final way and dynamicly:

```php
'odbc-connection-name' => [
    'driver' => 'snowflake',
    'odbc_driver' => '/opt/snowflake/snowflakeodbc/lib/universal/libSnowflake.dylib',
    // 'odbc_driver' => 'Snowflake Driver',
    'server' => 'host.example.com'
    // 'host' => 'hostname.example.com',
    'username' => 'username',
    'password' => 'password',
    'warehouse' => 'warehouse name',
    'schema' => 'PUBLIC', // majority odbc's is default
]
```

> All fields will be dynamicly added to the DSN connection string, except the following: `driver, odbc_driver, options, username, password` these will be filtered from the DSN (for now).

> Note: DSN `odbc_driver` can be a absolute path to your driver file or the name registered within `odbcinst.ini` file/ODBC manager.

## # Eloquen ORM

You can use Laravel, Eloquent ORM and other Illuminate's components as usual.

```PHP
# Facade
$books = DB::connection('odbc-connection-name')->table('books')->where('Author', 'Abram Andrea')->get();

# ORM
$books = Book::where('Author', 'Abram Andrea')->get();
```

## # Custom getLastInsertId() function

If you want to provide a custom <b>getLastInsertId()</b> function, you can extends _ODBCProcessor_ class and override function.<br>

```PHP
class CustomProcessor extends ODBCProcessor
{
    /**
     * @param Builder $query
     * @param null $sequence
     * @return mixed
     */
    public function getLastInsertId(Builder $query, $sequence = null)
    {
        return $query->getConnection()->table($query->from)->latest('id')->first()->getAttribute($sequence);
    }
}
```

## # Custom Processor / QueryGrammar / SchemaGrammar

To use another class instead default one you can update your connection in:

```PHP
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'OdbcConnectionName',
    'username' => 'username',
    'password' => 'password',
    'options' => [
        'processor' => Illuminate\Database\Query\Processors\Processor::class,   //default
        'grammar' => [
            'query' => Illuminate\Database\Query\Grammars\Grammar::class,       //default
            'schema' => Illuminate\Database\Schema\Grammars\Grammar::class      //default
        ]
    ]
]
```

## # Troubleshoot

#### `504 Bad gateway` error

This error can occure on Mac OS. Make sure you have the PHP extensions `odbc` and `PDO_ODBC` installed.
If you got the error `internal error, unexpected SHLIBEXT value` you should change the used cursor library:

```PHP
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'Driver={Your Snowflake Driver};Server=snowflake.example.com', // odbc: will be prefixed
    // ....
    'options' => [
      \PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => \PDO::ODBC_SQL_USE_DRIVER
      // or
      // 1000 => 2
    ]
]
// \PDO::ODBC_ATTR_USE_CURSOR_LIBRARY equals 1000
// \PDO::ODBC_SQL_USE_DRIVER equals 2
```

> Check other options [here](https://www.php.net/manual/en/ref.pdo-odbc.php)
