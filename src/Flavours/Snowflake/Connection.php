<?php

namespace LaravelPdoOdbc\Flavours\Snowflake;

use PDO;
use PDOStatement;
use function is_bool;
use function is_null;
use DateTimeInterface;
use function is_float;
use function is_string;
use LaravelPdoOdbc\ODBCConnection;

class Connection extends ODBCConnection
{
    /**
     * {@inheritdoc}
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Builders\Schema($this);
    }

    public function getDefaultQueryGrammar()
    {
        $queryGrammar = $this->getConfig('options.grammar.query');

        if ($queryGrammar) {
            return new $queryGrammar();
        }

        return new Grammars\Query();
    }

    public function getDefaultSchemaGrammar()
    {
        $schemaGrammar = $this->getConfig('options.grammar.schema');

        if ($schemaGrammar) {
            return new $schemaGrammar();
        }

        return new Grammars\Schema();
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param PDOStatement $statement
     * @param array        $bindings
     *
     * @return void
     */
    public function bindValues($statement, $bindings)
    {
        foreach ($bindings as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_bool($value)) {
                $value = $value ? 'TRUE' : 'FALSE';
            } elseif (is_numeric($value)) {
                $type = PDO::PARAM_INT;
            }

            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                $type
            );
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = (bool) $value;
            } elseif (is_float($value)) {
                $bindings[$key] = (float) $value;
            } elseif (is_numeric($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * Get the default post processor instance.
     *
     * @return ODBCProcessor
     */
    protected function getDefaultPostProcessor()
    {
        $processor = $this->getConfig('options.processor');

        if ($processor) {
            return new $processor();
        }

        return new Processor();
    }
}
