<?php

namespace LaravelPdoOdbc\Drivers\Snowflake;

use Illuminate\Database\Schema\MySqlBuilder as BaseBuilder;

class SchemaBuilder extends BaseBuilder
{
    /**
     * Get all of the table names for the database.
     *
     * @return array
     */
    public function getAllTables()
    {
        $tables = $this->connection->select('SHOW TABLES');

        return array_column($tables, 'name');
    }
}
