# Snowflake ODBC

Certain connections may have specific configuration issues that need to be
addressed for proper functionality.

There are currently 2 known quirks/issues related to a Snowflake ODBC connection.
These quirks do not apply when using the snowflake PHP extension.

1. By default, Snowflake ODBC executes `DDL` statements during preparation.
   This means that when you send a `$stmt = $pdo->prepare('...');` statement to
   Snowflake, it automatically executes it. To resolve this issue, you must disable
   the execution of DDL statements. Snowflake provides an ODBC Driver configuration
   option for this purpose: `NoExecuteInSQLPrepare=true`. This option has been
   available since version `2.21.6`.

   - For Windows environments, refer to the [Non-unix environment documentation](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#setting-parameters-in-windows).
   - For Unix environments, modify the `simba.snowflake.ini` file as described [here](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#setting-parameters-in-macos-or-linux).

   Additional information can be found in the following resources:

   - [ODBC parameters](https://docs.snowflake.com/en/user-guide/odbc-parameters.html#configuration-parameters).
   - [Changelog June 2020](https://community.snowflake.com/s/article/client-release-history).
   - [More about DDL]

2. Snowflake does not support streaming bind values. This limitation affects
   scenarios where you

use `$stmt->bindValue(...)` or `$stmt->bindParam()` after preparing the statement
using `->prepare('..')`. To address this issue, a custom `CustomStatement`
class has been added.
