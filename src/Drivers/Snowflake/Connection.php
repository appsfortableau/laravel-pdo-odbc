<?php

namespace LaravelPdoOdbc\Drivers\Snowflake;

use LaravelPdoOdbc\ODBCConnection;
use LaravelPdoOdbc\Grammars\Query\SnowflakeGrammar as QueryGrammer;
use LaravelPdoOdbc\Processors\SnowflakeProcessor as Processor;
use LaravelPdoOdbc\Grammars\Schema\SnowflakeGrammar as SchemaGrammer;

class Connection extends ODBCConnection
{
    /**
     * @inheritdoc
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

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
        dd('done', $this->statement($query, $bindings));
        return $this->statement($query, $bindings);
    }

    // /**
    //  * Bind values to their parameters in the given statement.
    //  *
    //  * @param  \PDOStatement  $statement
    //  * @param  array  $bindings
    //  * @return void
    //  */
    // public function bindValues($statement, $bindings)
    // {
    //     dd('done', $statement);
    //     foreach ($bindings as $key => $value) {
    //         $statement->bindValue(
    //             is_string($key) ? $key : $key + 1,
    //             $value,
    //             is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    //         );
    //     }
    // }
}
