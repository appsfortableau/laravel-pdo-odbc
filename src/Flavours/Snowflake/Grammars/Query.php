<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\Grammars;

use function is_array;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use LaravelPdoOdbc\Flavours\Snowflake\Concerns\GrammarHelper;

class Query extends Grammar
{
    use GrammarHelper {
        wrapTable as protected wrapTableIdentifier;
    }

    /**
     * Stack of table names/aliases for the queries currently being compiled.
     * Used to tell a "table.column" reference apart from a column whose name
     * contains a dot, e.g. "ex. Work Date".
     *
     * @var array<int, string[]>
     */
    protected array $compilingTables = [];

    /**
     * True while a table name is being wrapped, so "db.schema.table" keeps
     * being split into segments.
     */
    protected bool $wrappingTable = false;

    /**
     * All of the available clause operators.
     *
     * @var string[]
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like',
        'ilike', 'not ilike',
        // '&', '|', '<<', '>>',
    ];

    /**
     * Determine if this connection should compile LIKE as ILIKE by default.
     */
    protected function shouldUseIlike(): bool
    {
        $value = $this->connection?->getConfig('options.use_ilike');

        return $value === null ? true : (bool) $value;
    }

    /**
     * Compile a basic where clause.
     *
     * Snowflake supports both LIKE (case-sensitive) and ILIKE (case-insensitive).
     * This package defaults to ILIKE for broader compatibility with typical
     * Laravel expectations (case-insensitive search), but can be disabled via:
     * `options.use_ilike = false`.
     *
     * @param  array  $where
     */
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower($where['operator']);

        if ($this->shouldUseIlike()) {
            if ($operator === 'like') {
                $where['operator'] = 'ilike';
            } elseif ($operator === 'not like') {
                $where['operator'] = 'not ilike';
            }
        }

        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$where['operator'].' '.$value;
    }

    /**
     * Compile a "where like" clause.
     *
     * @param  array  $where
     */
    protected function whereLike(Builder $query, $where)
    {
        $caseSensitive = (bool) ($where['caseSensitive'] ?? false);
        $operator = $caseSensitive ? 'like' : 'ilike';

        if (($where['not'] ?? false) === true) {
            $operator = 'not '.$operator;
        }

        $value = $this->parameter($where['value']);

        return $this->wrap($where['column']).' '.$operator.' '.$value;
    }

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'at',
        'before',
        'pivot',
        'unpivot',
        'values',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * Compile an update statement into SQL.
     *
     * @return string
     */
    public function compileUpdate(Builder $query, array $values)
    {
        return $this->withQueryTables($query, function () use ($query, $values) {
            if (isset($query->joins) || isset($query->limit)) {
                return $this->compileUpdateWithJoinsOrLimit($query, $values);
            }

            return parent::compileUpdate($query, $values);
        });
    }

    /**
     * Compile a select query into SQL.
     *
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        return $this->withQueryTables($query, fn () => parent::compileSelect($query));
    }

    /**
     * Run the callback with the tables of the given query registered, so that
     * wrap() only splits off a prefix that is a real table or alias.
     */
    protected function withQueryTables(Builder $query, Closure $callback)
    {
        $tables = [];

        foreach (array_merge([$query->from], array_map(fn ($join) => $join->table, $query->joins ?? [])) as $table) {
            if (! is_string($table)) {
                continue;
            }

            $segments = preg_split('/\s+as\s+/i', $table);
            $tables[] = $segments[0];
            $tables[] = last(explode('.', $segments[0]));

            if (isset($segments[1])) {
                $tables[] = $segments[1];
            }
        }

        $this->compilingTables[] = array_unique($tables);

        try {
            return $callback();
        } finally {
            array_pop($this->compilingTables);
        }
    }

    /**
     * Find the table or alias the identifier is prefixed with, if any.
     */
    protected function matchTablePrefix(string $value): ?string
    {
        $tables = array_merge(...($this->compilingTables ?: [[]]));

        // longest first, so "DB.SCHEMA.TABLE" wins over "TABLE"
        usort($tables, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($tables as $table) {
            if (str_starts_with($value, $table.'.')) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Wrap a value in keyword identifiers.
     *
     * While a query is compiled, a dot only separates table and column when the
     * part before it is one of the query's tables or aliases. Otherwise the dot
     * is part of the column name.
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $value
     * @return string
     */
    public function wrap($value)
    {
        if ($this->wrappingTable
            || empty($this->compilingTables)
            || $this->isExpression($value)
            || ! str_contains($value, '.')
            || stripos($value, ' as ') !== false
            || $this->isJsonSelector($value)
        ) {
            return parent::wrap($value);
        }

        if ($table = $this->matchTablePrefix($value)) {
            return $this->wrapTable($table).'.'.$this->wrapColumn(substr($value, strlen($table) + 1));
        }

        return $this->wrapColumn($value);
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @return string
     */
    public function wrapTable($table, $prefix = null)
    {
        $wrapping = $this->wrappingTable;
        $this->wrappingTable = true;

        try {
            return $this->wrapTableIdentifier($table, $prefix);
        } finally {
            $this->wrappingTable = $wrapping;
        }
    }

    /**
     * Compile an insert ignore statement into SQL.
     *
     * @return string
     */
    public function compileInsertOrIgnore(Builder $query, array $values)
    {
        return Str::replaceFirst('insert', 'insert or ignore', $this->compileInsert($query, $values));
    }

    /**
     * Compile an "upsert" statement into SQL.
     *
     * @return string
     */
    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update)
    {
        $sql = $this->compileInsert($query, $values);

        $sql .= ' on conflict ('.$this->columnize($uniqueBy).') do update set ';

        $columns = collect($update)->map(function ($value, $key) {
            return is_numeric($key)
                ? $this->wrap($value).' = '.$this->wrapValue('excluded').'.'.$this->wrap($value)
                : $this->wrap($key).' = '.$this->parameter($value);
        })->implode(', ');

        return $sql.$columns;
    }

    /**
     * Prepare the bindings for an update statement.
     *
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        $groups = $this->groupJsonColumnsForUpdate($values);

        $values = collect($values)->reject(function ($value, $key) {
            return $this->isJsonSelector($key);
        })->merge($groups)->map(function ($value) {
            return is_array($value) ? json_encode($value) : $value;
        })->all();

        $cleanBindings = Arr::except($bindings, 'select');

        return array_values(
            array_merge($values, Arr::flatten($cleanBindings))
        );
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        return $this->withQueryTables($query, function () use ($query) {
            if (isset($query->joins) || isset($query->limit)) {
                return $this->compileDeleteWithJoinsOrLimit($query);
            }

            return parent::compileDelete($query);
        });
    }

    /**
     * Compile a truncate table statement into SQL.
     *
     * @return array
     */
    public function compileTruncate(Builder $query)
    {
        return 'trunate table '.$this->wrapTable($query->from);
    }

    /**
     * Compile an insert statement into SQL.
     *
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        return parent::compileInsert($query, $values);
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param array $aggregate
     *
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if (is_array($query->distinct)) {
            $column = 'distinct '.$this->columnize($query->distinct);
        } elseif ($query->distinct && '*' !== $column) {
            $column = 'distinct '.$column;
        }

        return 'select '.$aggregate['function'].'('.$column.') as "aggregate"';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param bool|string $value
     *
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        return '';
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * @param string $sql
     *
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return 'select * from ('.$sql.')';
    }

    /**
     * Compile a "where date" clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereDate(Builder $query, $where)
    {
        return $this->dateBasedWhere('%Y-%m-%d', $query, $where);
    }

    /**
     * Compile a "where day" clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereDay(Builder $query, $where)
    {
        return $this->dateBasedWhere('%d', $query, $where);
    }

    /**
     * Compile a "where month" clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereMonth(Builder $query, $where)
    {
        return $this->dateBasedWhere('%m', $query, $where);
    }

    /**
     * Compile a "where year" clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereYear(Builder $query, $where)
    {
        return $this->dateBasedWhere('%Y', $query, $where);
    }

    /**
     * Compile a "where time" clause.
     *
     * @param array $where
     *
     * @return string
     */
    protected function whereTime(Builder $query, $where)
    {
        return $this->dateBasedWhere('%H:%M:%S', $query, $where);
    }

    /**
     * Compile a date based where clause.
     *
     * @param string $type
     * @param array  $where
     *
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $column = $this->wrap($where['column']);
        $value = $this->parameter($where['value']);

        // For full date comparisons rely on a native date cast instead of string formatting
        if ('%Y-%m-%d' === $type) {
            return "{$column} {$where['operator']} {$value}::DATE";
        }

        // Map generic formats to Snowflake TO_VARCHAR patterns
        $format = match ($type) {
            '%Y-%m-%d' => 'YYYY-MM-DD',
            '%d' => 'DD',
            '%m' => 'MM',
            '%Y' => 'YYYY',
            '%H:%M:%S' => 'HH24:MI:SS',
            default => 'YYYY-MM-DD',
        };

        return "TO_VARCHAR({$column}, '{$format}') {$where['operator']} {$value}";
    }

    /**
     * Compile the columns for an update statement.
     *
     * @return string
     */
    protected function compileUpdateColumns(Builder $query, array $values)
    {
        $jsonGroups = $this->groupJsonColumnsForUpdate($values);

        return collect($values)->reject(function ($value, $key) {
            return $this->isJsonSelector($key);
        })->merge($jsonGroups)->map(function ($value, $key) use ($jsonGroups) {
            // only strip a real "table." prefix, a dot can be part of the column name
            $table = $this->matchTablePrefix($key);
            $column = $table ? substr($key, strlen($table) + 1) : $key;

            $value = isset($jsonGroups[$key]) ? $this->compileJsonPatch($column, $value) : $this->parameter($value);

            return $this->wrapColumn($column).' = '.$value;
        })->implode(', ');
    }

    /**
     * Group the nested JSON columns.
     *
     * @return array
     */
    protected function groupJsonColumnsForUpdate(array $values)
    {
        $groups = [];

        foreach ($values as $key => $value) {
            if ($this->isJsonSelector($key)) {
                Arr::set($groups, str_replace('->', '.', Str::after($key, '.')), $value);
            }
        }

        return $groups;
    }

    /**
     * Compile an update statement with joins or limit into SQL.
     *
     * @return string
     */
    protected function compileUpdateWithJoinsOrLimit(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $alias = last(preg_split('/\s+as\s+/i', $query->from));

        $selectSql = $this->compileSelect($query->select($alias.'.rowid'));

        return "update {$table} set {$columns} where {$this->wrap('rowid')} in ({$selectSql})";
    }

    /**
     * Compile a delete statement with joins or limit into SQL.
     *
     * @return string
     */
    protected function compileDeleteWithJoinsOrLimit(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $alias = last(preg_split('/\s+as\s+/i', $query->from));

        $selectSql = $this->compileSelect($query->select($alias.'.rowid'));

        return "delete from {$table} where {$this->wrap('rowid')} in ({$selectSql})";
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapJsonSelector($value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'get_path('.$field.', "'.$path.'")';
    }

    /**
     * Escapes a value for safe SQL embedding.
     *
     * @param  string|float|int|bool|null  $value
     * @param  bool  $binary
     * @return string
     */
    public function escape($value, $binary = false)
    {
        return DB::connection()->getPdo()->quote($value);
    }
}
