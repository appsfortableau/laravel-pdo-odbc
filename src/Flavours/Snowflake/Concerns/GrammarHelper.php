<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\Concerns;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\ColumnDefinition;
use LaravelPdoOdbc\Flavours\Snowflake\Processor;
use Illuminate\Support\Str;

use function count;

/**
 * This code is shared between the Query and Schema grammar.
 * Mainly for correcting the values and columns.
 *
 * Values: are wrapped within single qoutes.
 * Columns and Table names: are wrapped within double qoutes.
 */
trait GrammarHelper
{
    /**
     * Convert an array of column names into a delimited string.
     *
     * @return string
     */
    public function columnize(array $columns)
    {
        return implode(', ', array_map([$this, 'wrapColumn'], $columns));
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param \Illuminate\Database\Query\Expression|Illuminate\Database\Schema\Blueprint|string $table
     *
     * @return string
     */
    public function wrapTable($table, $prefix = null)
    {
        if (method_exists($this, 'isExpression') && !$this->isExpression($table)) {
            $table = Processor::wrapTable($table);
            return $this->wrap($prefix . $table, true);
        }

        return $this->getValue($table);
    }

    /**
     * Get the value of a raw expression.
     *
     * @param \Illuminate\Database\Query\Expression $expression
     *
     * @return string
     */
    public function getValue($expression)
    {
        return $expression instanceof Expression ? $expression->getValue($this) : $expression;
    }

    /**
     * Wrap the given value segments.
     *
     * @param array $segments
     *
     * @return string
     */
    protected function wrapSegments($segments)
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return 0 === $key && count($segments) > 1
                ? $this->wrapTable($segment)
                // Original ->wraValue, but this is always called for columns segments
                : $this->wrapColumn($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param string | \Illuminate\Database\Query\Expression $column
     *
     * @return string
     */
    protected function wrapColumn($column)
    {
        if (method_exists($this, 'isExpression') && $this->isExpression($column)) {
            return $this->getValue($column);
        }

        if ($column instanceof ColumnDefinition) {
            $column = $column->get('name');
        }

        if ('*' !== $column) {
            if (! env('SNOWFLAKE_COLUMNS_CASE_SENSITIVE', false)) {
                return str_replace('"', '', Str::upper($column));
            }

            return '"'.str_replace('"', '""', $column).'"';
        }

        return $column;
    }

    /**
     * Wrap a single string in keypublic function wrapTable($table)word identifiers.
     *
     * @param string $value
     *
     * @return string
     */
    protected function wrapValue($value)
    {
        if ('*' !== $value) {
            return "'".str_replace("'", "''", $value)."'";
        }

        return $value;
    }
}
