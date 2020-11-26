<?php

namespace LaravelPdoOdbc;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\Processor;

class ODBCConnection extends Connection
{
    public function getDefaultQueryGrammar()
    {
        $queryGrammar = $this->getConfig('options.grammar.query');

        if ($queryGrammar) {
            return new $queryGrammar();
        }

        return parent::getDefaultQueryGrammar();
    }

    public function getDefaultSchemaGrammar()
    {
        $schemaGrammar = $this->getConfig('options.grammar.schema');

        if ($schemaGrammar) {
            return new $schemaGrammar();
        }

        return parent::getDefaultSchemaGrammar();
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
