<?php

namespace LaravelPdoOdbc\Drivers\Snowflake;

use LaravelPdoOdbc\ODBCConnection;
use Illuminate\Database\Query\Grammars\MySqlGrammar as QueryGrammer;
use Illuminate\Database\Query\Processors\MySqlProcessor as Processor;
use Illuminate\Database\Schema\Grammars\MySqlGrammar as SchemaGrammer;

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
}
