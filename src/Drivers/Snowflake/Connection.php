<?php

namespace LaravelPdoOdbc\Drivers\Snowflake;

use function count;
use function is_null;
use LaravelPdoOdbc\ODBCConnection;
use LaravelPdoOdbc\Processors\SnowflakeProcessor as Processor;
use LaravelPdoOdbc\Grammars\Query\SnowflakeGrammar as QueryGrammer;
use LaravelPdoOdbc\Grammars\Schema\SnowflakeGrammar as SchemaGrammer;

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

        return new SchemaBuilder($this);
    }

    public function getDefaultQueryGrammar()
    {
        $queryGrammar = $this->getConfig('options.grammar.query');

        if ($queryGrammar) {
            return new $queryGrammar();
        }

        return new QueryGrammer();
    }

    public function getDefaultSchemaGrammar()
    {
        $schemaGrammar = $this->getConfig('options.grammar.schema');

        if ($schemaGrammar) {
            return new $schemaGrammar();
        }

        return new SchemaGrammer();
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            // only use the prepare if there are bindings
            if (0 === count($bindings)) {
                $affected = $this->getPdo()->query($query);

                if (false === $affected) {
                    $err = $conn->errorInfo();
                    if ('00000' === $err[0] || '01000' === $err[0]) {
                        return true;
                    }
                }

                return (bool) $affected;
            }

            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
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
