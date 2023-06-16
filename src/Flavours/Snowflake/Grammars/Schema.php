<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\Grammars;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar as BaseGrammar;
use Illuminate\Support\Fluent;
use LaravelPdoOdbc\Flavours\Snowflake\Concerns\GrammarHelper;
use RuntimeException;
use function in_array;
use function is_float;
use function is_int;
use function is_null;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * More documentation on Snowflake columns:
 *     https://docs.snowflake.com/en/sql-reference/intro-summary-data-types.html
 * and even semi structures (usefull for JSON stuff):
 *     https://docs.snowflake.com/en/sql-reference/data-types-semistructured.html.
 *
 * Rules for altering can be found here:
 *     https://docs.snowflake.com/en/sql-reference/sql/alter-table-column.html
 */
class Schema extends BaseGrammar
{
    use GrammarHelper;

    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = [
        'Charset', 'Collate', 'VirtualAs', 'StoredAs', 'Nullable',
        'Srid', 'Default', 'Increment', 'Comment', 'After', 'First',
    ];

    /**
     * The possible column serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'smallInteger'];

    /**
     * Compile the query to determine the list of tables.
     *
     * @return string
     */
    public function compileTableExists()
    {
        return "select * from information_schema.tables where table_catalog = ? and table_name = ? and table_type = 'BASE TABLE'";
    }

    /**
     * Compile the query to determine the list of tables.
     *
     * @return string
     */
    public function compileTableDetails(string $table)
    {
        return 'select * from information_schema.tables where table_name = \''.$table.'\' order by ordinal_position';
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @return string
     */
    public function compileColumnListing()
    {
        return 'select column_name as "column_name", data_type as "column_type", lower(is_nullable) as "is_nullable" from {DB_NAME}.information_schema.columns where table_catalog = ? and table_name = ?';
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @return string
     */
    public function compileGetColumnType()
    {
        return 'select column_name as "column_name", data_type as "column_type", numeric_precision as "numeric_precision", numeric_scale as "numeric_scale" from {DB_NAME}.information_schema.columns where table_name = ? and column_name = ?';
    }

    /**
     * Compile a rename column command.
     *
     * Snowflake documentation found here: https://docs.snowflake.com/en/sql-reference/sql/alter-table.html
     *
     * @return array
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return sprintf(
            'alter table %s rename column %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile a create table command.
     *
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $sql = $this->compileCreateTable(
            $blueprint,
            $command,
            $connection
        );

        // Once we have the primary SQL, we can add the encoding option to the SQL for
        // the table.  Then, we can check if a storage engine has been supplied for
        // the table. If so, we will add the engine declaration to the SQL query.
        $sql = $this->compileCreateEncoding(
            $sql,
            $connection,
            $blueprint
        );

        // Finally, we will append the engine configuration onto this SQL statement as
        // the final thing we do before returning this finished SQL. Once this gets
        // added the query will be ready to execute against the real connections.
        return $this->compileCreateEngine(
            $sql,
            $connection,
            $blueprint
        );
    }

    /**
     * Compile an add column command.
     *
     * @return string
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $prefix = 'alter table '.$this->wrapTable($blueprint).' add column';
        return $this->prefixArray($prefix, $this->getColumns($blueprint));
    }

    /**
     * Compile an add column command.
     *
     * @return array
     */
    public function compileChangeColumn(Blueprint $blueprint, Fluent $command)
    {
        $prefix = sprintf('alter table %s modify column', $this->wrapTable($blueprint));
        $columns = $this->prefixArray($prefix, $this->getChangedColumns($blueprint));

        return array_values(array_merge(
            $columns,
            $this->compileAutoIncrementStartingValues($blueprint, $command)
        ));
    }

    /**
     * Compile the auto incrementing column starting values.
     *
     * @return string
     */
    public function compileAutoIncrementStartingValues(Blueprint $blueprint, Fluent $command)
    {
        if ($command->column->autoIncrement && $value = $command->column->get('startingValue', $command->column->get('from'))) {
            return 'alter table '.$this->wrapTable($blueprint).' autoincrement start '.$value;
        }
    }

    /**
     * Compile a primary key command.
     *
     * @return string
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command)
    {
        $command->name(null);

        return $this->compileKey($blueprint, $command, 'primary key');
    }

    /**
     * Compile a unique key command.
     *
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'unique');
    }

    /**
     * Compile a plain index key command.
     *
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'index');
    }

    /**
     * Compile a spatial index key command.
     *
     * @return string
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileKey($blueprint, $command, 'spatial index');
    }

    /**
     * Compile a drop table command.
     *
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @return string
     */
    public function compileDropDatabaseIfExists($name)
    {
        return 'drop database if exists '.$this->wrapTable($name);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @return string
     */
    public function compileDropDatabase($name)
    {
        return 'drop database '.$this->wrapTable($name);
    }

    /**
     * Compile a create database command.
     *
     * @param string                          $name
     * @param \Illuminate\Database\Connection $connection
     *
     * @return string
     */
    public function compileCreateDatabase($name, $connection)
    {
        return sprintf(
            'create database %s',
            $this->wrapTable($name)
        );
    }

    /**
     * Compile a drop column command.
     *
     * @return string
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command.
     *
     * @return string
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    /**
     * Compile a drop unique key command.
     *
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop index command.
     *
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop spatial index command.
     *
     * @return string
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     *
     * @return string
     */
    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop foreign key {$index}";
    }

    /**
     * Compile a rename table command.
     *
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     *
     * @return string
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'alter table %s rename index %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @param array $tables
     *
     * @return string
     */
    public function compileDropAllTables($tables)
    {
        return 'drop table '.implode(',', $this->wrapArray($tables));
    }

    /**
     * Compile the SQL needed to drop all views.
     *
     * @param array $views
     *
     * @return string
     */
    public function compileDropAllViews($views)
    {
        return 'drop view '.implode(',', $this->wrapArray($views));
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     */
    public function compileGetAllTables()
    {
        return 'SHOW TABLES';
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     */
    public function compileGetAllViews()
    {
        return 'SHOW VIEWS';
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=1;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'SET FOREIGN_KEY_CHECKS=0;';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @return string
     */
    public function typeGeometry(Fluent $column)
    {
        return 'geometry';
    }

    /**
     * Create the column definition for a spatial Point type.
     *
     * @return string
     */
    public function typePoint(Fluent $column)
    {
        return 'point';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @return string
     */
    public function typeLineString(Fluent $column)
    {
        return 'linestring';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @return string
     */
    public function typePolygon(Fluent $column)
    {
        return 'polygon';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @return string
     */
    public function typeGeometryCollection(Fluent $column)
    {
        return 'geometrycollection';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @return string
     */
    public function typeMultiPoint(Fluent $column)
    {
        return 'multipoint';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @return string
     */
    public function typeMultiLineString(Fluent $column)
    {
        return 'multilinestring';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @return string
     */
    public function typeMultiPolygon(Fluent $column)
    {
        return 'multipolygon';
    }

    /**
     * Compile a change column command into a series of SQL statements.
     *
     * @throws RuntimeException
     *
     * @return array
     */
    public function compileChange(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return ChangeColumn::compile($this, $blueprint, $command, $connection);
    }

    /**
     * Create the main create table clause.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent            $command
     * @param \Illuminate\Database\Connection       $connection
     *
     * @return array
     */
    protected function compileCreateTable($blueprint, $command, $connection)
    {
        return trim(sprintf(
            '%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        ));
    }

    /**
     * Append the character set specifications to a command.
     *
     * @param string $sql
     *
     * @return string
     */
    protected function compileCreateEncoding($sql, Connection $connection, Blueprint $blueprint)
    {
        // First we will set the character set if one has been set on either the create
        // blueprint itself or on the root configuration for the connection that the
        // table is being created on. We will add these to the create table query.
        if (isset($blueprint->charset)) {
            $sql .= ' default character set '.$blueprint->charset;
        } elseif (! is_null($charset = $connection->getConfig('charset'))) {
            $sql .= ' default character set '.$charset;
        }

        // Next we will add the collation to the create table statement if one has been
        // added to either this create table blueprint or the configuration for this
        // connection that the query is targeting. We'll add it to this SQL query.
        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (! is_null($collation = $connection->getConfig('collation'))) {
            $sql .= " collate '{$collation}'";
        }

        return $sql;
    }

    /**
     * Append the engine specifications to a command.
     *
     * @param string $sql
     *
     * @return string
     */
    protected function compileCreateEngine($sql, Connection $connection, Blueprint $blueprint)
    {
        if (isset($blueprint->engine)) {
            return $sql.' engine = '.$blueprint->engine;
        } elseif (! is_null($engine = $connection->getConfig('engine'))) {
            return $sql.' engine = '.$engine;
        }

        return $sql;
    }

    /**
     * Compile an index creation command.
     *
     * @param string $type
     *
     * @return string
     */
    protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
    {
        return sprintf(
            'alter table %s add constraint %s %s %s(%s)',
            $this->wrapTable($blueprint),
            $this->wrap($command->index),
            $type,
            $command->algorithm ? ' using '.$command->algorithm : '',
            $this->columnize($command->columns)
        );
    }

    /**
     * Create the column definition for a char type.
     *
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     *
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return "varchar({$column->length})";
    }

    /**
     * Create the column definition for a text type.
     *
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'mediumtext';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'longtext';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'bigint';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'int';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a float type.
     *
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        if ($column->total && $column->places) {
            return "float({$column->total}, {$column->places})";
        }

        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        if ($column->total && $column->places) {
            return "double({$column->total}, {$column->places})";
        }

        return 'double';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'boolean';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return sprintf('enum(%s)', $this->quoteString($column->allowed));
    }

    /**
     * Create the column definition for a set enumeration type.
     *
     * @return string
     */
    protected function typeSet(Fluent $column)
    {
        return sprintf('set(%s)', $this->quoteString($column->allowed));
    }

    /**
     * Create the column definition for a json type.
     *
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'object';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'object';
    }

    /**
     * Create the column definition for a date type.
     *
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        $columnType = $column->precision ? "datetime($column->precision)" : 'datetime';

        return $column->useCurrent ? "$columnType default CURRENT_TIMESTAMP" : $columnType;
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type.
     *
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return $column->precision ? "time($column->precision)" : 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @return string
     */
    protected function typeTimeTz(Fluent $column)
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        $columnType = $column->precision ? "timestamp($column->precision)" : 'timestamp';

        $current = $column->precision ? "CURRENT_TIMESTAMP($column->precision)" : 'CURRENT_TIMESTAMP';

        $columnType = $column->useCurrent ? "$columnType default $current" : $columnType;

        return $column->useCurrentOnUpdate ? "$columnType on update $current" : $columnType;
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type.
     *
     * @return string
     */
    protected function typeYear(Fluent $column)
    {
        return 'year';
    }

    /**
     * Create the column definition for a binary type.
     *
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar(17)';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     * @throws RuntimeException
     *
     * @return void
     */
    protected function typeComputed(Fluent $column)
    {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }

    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @return string|null
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->virtualAs)) {
            return " as ({$column->virtualAs})";
        }
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @return string|null
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->storedAs)) {
            return " as ({$column->storedAs}) stored";
        }
    }

    /**
     * Get the SQL for an unsigned column modifier.
     *
     * @return string|null
     */
    protected function modifyUnsigned(Blueprint $blueprint, Fluent $column)
    {
        // if ($column->unsigned) {
        //     return ' unsigned';
        // }
        return '';
    }

    /**
     * Get the SQL for a character set column modifier.
     *
     * @return string|null
     */
    protected function modifyCharset(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->charset)) {
            return ' character set '.$column->charset;
        }
    }

    /**
     * Get the SQL for a collation column modifier.
     *
     * @return string|null
     */
    protected function modifyCollate(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->collation)) {
            return " collate '{$column->collation}'";
        }
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        if (is_null($column->virtualAs) && is_null($column->storedAs)) {
            return $column->nullable ? ' null' : ' not null';
        }

        if (false === $column->nullable) {
            return ' not null';
        }
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default, $column->type);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials, true) && $column->autoIncrement) {
            return ' autoincrement primary key';
        }
    }

    /**
     * Get the SQL for a "first" column modifier.
     *
     * @return string|null
     */
    protected function modifyFirst(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->first)) {
            return ' first';
        }
    }

    /**
     * Get the SQL for an "after" column modifier.
     *
     * @return string|null
     */
    protected function modifyAfter(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->after)) {
            return ' after '.$this->wrap($column->after);
        }
    }

    /**
     * Get the SQL for a "comment" column modifier.
     *
     * @return string|null
     */
    protected function modifyComment(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->comment)) {
            return " comment '".addslashes($column->comment)."'";
        }
    }

    /**
     * Get the SQL for a SRID column modifier.
     *
     * @return string|null
     */
    protected function modifySrid(Blueprint $blueprint, Fluent $column)
    {
        if (! is_null($column->srid) && is_int($column->srid) && $column->srid > 0) {
            return ' srid '.$column->srid;
        }
    }

    /**
     * Compile the blueprint's column definitions.
     *
     * @return array
     */
    protected function getColumns(Blueprint $blueprint)
    {
        return $this->handleNullables(
            $this->getColumnModifiers($blueprint->getAddedColumns(), $blueprint),
            false // mode Change
        );
    }

    /**
     * Compile the blueprint's column definitions for changed columns.
     *
     * @return array
     */
    protected function getChangedColumns(Blueprint $blueprint)
    {
        // by default all columns are nullable only keep not null on change
        return $this->handleNullables(
            $this->getColumnModifiers($blueprint->getChangedColumns(), $blueprint),
            true // mode Change
        );
    }

    /**
     * Handle NULL or NOT NULL statements from within the queries.
     * Make seperate query's and push them into the columns array.
     */
    protected function handleNullables(array $columns, bool $isChanging = false): array
    {
        // get current state of the table
        foreach ($columns as $i => $column) {
            // on adding columns to the table
            if (! $isChanging) {
                if (! str_contains($column, ' not null') && str_contains($column, ' null')) {
                    $column = str_replace(' null', '', $column);
                }
            }
            // when changing the table
            elseif ($isChanging) {
                // handle nullables
                if (str_contains($column, ' not null')) {
                    // query: "column" set not null
                    preg_match('/(\".+\"\s)/', $column, $match);
                    if (count($match) === 0) {
                        $match = explode(' ', $column);
                    }

                    $columns[] = trim($match[0]).' set not null';
                    $column = str_replace(' not null', '', $column);
                } elseif (str_contains($column, ' null')) {
                    // query: "column" drop not null
                    preg_match('/(\".+\"\s)/', $column, $match);
                    $columns[] = trim($match[0]).' drop not null';
                    $column = str_replace(' null', '', $column);
                }

                // handle defaults, only sequence changes
                if (str_contains($column, 'default') && ! str_contains($column, '.nextval')) {
                    $split = explode('default', $column);
                    $column = reset($split);
                }
            }

            $columns[$i] = trim($column);
        }

        return $columns;
    }

    /**
     * Generate columns based on given Blueprint.
     *
     * @return array
     */
    protected function getColumnModifiers(array $columns, Blueprint $blueprint)
    {
        $parsedColumns = [];

        foreach ($columns as $column) {
            // Each of the column types have their own compiler functions which are tasked
            // with turning the column definition into its SQL format for this platform
            // used by the connection. The column's modifiers are compiled and added.
            $sql = str_replace("'", '"', $this->wrapColumn($column)).' '.$this->getType($column);

            $parsedColumns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $parsedColumns;
    }

    /**
     * Format a value so that it can be used in "default" clauses.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function getDefaultValue($value, $type = null)
    {
        if ($value instanceof Expression) {
            return $value;
        }

        if ('boolean' === $type) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
        } elseif (is_float($value)) {
            return (float) $value;
        } elseif (is_numeric($value)) {
            return (int) $value;
        }

        return "'".(string) $value."'";
    }
}
