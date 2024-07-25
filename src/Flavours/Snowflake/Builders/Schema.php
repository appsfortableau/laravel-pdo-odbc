<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\Builders;

use LogicException;
use function count;
use function in_array;
use Illuminate\Database\Schema\Builder as BaseBuilder;
use LaravelPdoOdbc\Flavours\Snowflake\Concerns\GrammarHelper;

class Schema extends BaseBuilder
{
    use GrammarHelper;

    /**
     * Determine if the given table exists.
     *
     * @param string $table
     *
     * @return bool
     */
    public function hasTable($table)
    {
        $table = $this->connection->getTablePrefix().$this->wrapTable($table);

        return count($this->connection->select(
            $this->grammar->compileTableExists(),
            [$this->connection->getDatabaseName(), $table]
        )) > 0;
    }

    /**
     * Determine if the given table has a given column.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        return in_array(
            strtolower($column),
            array_map('strtolower', $this->getColumnListing($table)),
            true
        );
    }

    /**
     * Get the column listing for a given table.
     *
     * @param string $table
     *
     * @return array
     */
    public function getColumnListing($table)
    {
        $table = $this->connection->getTablePrefix().$this->wrapTable($table);

        $results = $this->connection->select(
            str_replace('{DB_NAME}', $this->connection->getDatabaseName(), $this->grammar->compileColumnListing()),
            [$this->connection->getDatabaseName(), $table]
        );

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Drop all tables from the database.
     *
     * @return void
     */
    public function dropAllTables()
    {
        $tables = [];

        foreach ($this->getAllTables() as $row) {
            $row = (array) $row;

            $tables[] = reset($row);
        }

        if (empty($tables)) {
            return;
        }

        $this->disableForeignKeyConstraints();

        $this->connection->statement(
            $this->grammar->compileDropAllTables($tables)
        );

        $this->enableForeignKeyConstraints();
    }

    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function dropAllViews()
    {
        $views = [];

        foreach ($this->getAllViews() as $row) {
            $row = (array) $row;

            $views[] = reset($row);
        }

        if (empty($views)) {
            return;
        }

        $this->connection->statement(
            $this->grammar->compileDropAllViews($views)
        );
    }

    /**
     * Drop a database from the schema if the database exists.
     *
     * @param string $name
     *
     * @throws LogicException
     *
     * @return bool
     */
    public function dropDatabaseIfExists($name)
    {
        $this->connection->statement(
            $this->grammar->compileDropDatabaseIfExists($name)
        );
    }

    /**
     * Drop a database from the schema.
     *
     * @param string $name
     *
     * @throws LogicException
     *
     * @return bool
     */
    public function dropDatabase($name)
    {
        $this->connection->statement(
            $this->grammar->compileDropDatabase($name)
        );
    }

    /**
     * Create a database in the schema.
     *
     * @param string $name
     *
     * @return bool
     */
    public function createDatabase($name)
    {
        return $this->connection->statement(
            $this->grammar->compileCreateDatabase($name, $this->connection)
        );
    }

    /**
     * Get all of the table names for the database.
     *
     * @return array
     */
    public function getAllTables()
    {
        $tables = $this->connection->select($this->grammar->compileGetAllTables());

        return array_column($tables, 'name');
    }

    /**
     * Get all of the view names for the database.
     *
     * @return array
     */
    public function getAllViews()
    {
        return $this->connection->select(
            $this->grammar->compileGetAllViews()
        );
    }

    /**
     * Get the data type for the given column name.
     *
     * @param string $table
     * @param string $column
     *
     * @return string
     */
    public function getColumnType($table, $column, $fullDefinition = false)
    {
        $table = $this->connection->getTablePrefix().$table;

        $record = $this->connection->select(
            str_replace('{DB_NAME}', $this->connection->getDatabaseName(), $this->grammar->compileGetColumnType()),
            [$table, $column]
        );
        $record = reset($record);

        if (! $record) {
            return null;
        }

        $record->numeric_precision = (int) $record->numeric_precision;
        $record->numeric_scale = (int) $record->numeric_scale;

        return $record;
    }
}
