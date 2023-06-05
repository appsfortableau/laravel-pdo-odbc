# Troubleshooting

## Insert queries begin executed twice

If you encounter problems with certain queries being executed twice, refer to
[issue #8](https://github.com/yoramdelangen/laravel-pdo-odbc/issues/8).

## `504 Bad gateway` Error

This error can occur on macOS. Make sure you have the PHP extensions `odbc` and
`PDO_ODBC` installed. If you receive the error message
`internal error, unexpected SHLIBEXT value`, you should change the used cursor
library as shown below:

```php
'odbc-connection-name' => [
    'driver' => 'odbc',
    // odbc: will be prefixed
    'dsn' => 'Driver={Your Snowflake Driver};Server=snowflake.example.com',
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

> Check other available options [here](https://www.php.net/manual/en/ref.pdo-odbc.php)
