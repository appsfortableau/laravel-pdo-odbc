# ODBC integration for Laravel Framework

This intergrates almost natively with Laravel Eloquent. Its a fork from `abram/laravel-odbc` to make it standalone next the `illuminate/database` without Laravel. But it will run with Laravel.

This package does not use the `odbc_*` functions, but the `PDO` class to make the intergration with eloquent much easier and more flawless.

## # How to install

> `composer require yoramdelangen/laravel-pdo-odbc` To add source in your project

## # Configuration

**Snowflake specific configuration**
There is some customization allowed with the Snowflake driver:
```
# when `false` it automaticly uppercases the column names
SNOWFLAKEP_COLUMNS_CASE_SENSITIVE=false

# When `true` it wraps the columns in double qoutes and makes them upper/lower case based on the input.
SNOWFLAKEP_COLUMNS_CASE_SENSITIVE=true
```

## # Usage Instructions

It's very simple to configure:

**1) Add database to database.php file**

```PHP
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'OdbcConnectionName', // odbc: will be prefixed
    'database' => 'DatabaseName',
    'host' => '127.0.0.1',
    'username' => 'username',
    'password' => 'password'
]
```

or when you do not have a datasource configured within your ODBC Manager:

```PHP
'odbc-connection-name' => [
    'driver' => 'odbc',
    'dsn' => 'Driver={Your Snowflake Driver};Server=snowflake.example.com;Port=443', // odbc: will be prefixed
    'database' => 'DatabaseName',
    'host' => '127.0.0.1',
    'username' => 'username',
    'password' => 'password'
]
```

> It showcases an example of using Snowflake.

**2) Add service provider in app.php file**

```PHP
'providers' => [
  ...
  LaravelPdoOdbc\ODBCServiceProvider::class
]
```

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
    'database' => 'DatabaseName',
    'host' => '127.0.0.1',
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
