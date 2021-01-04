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
}
