<?php

namespace LaravelPdoOdbc\Processors;

use Illuminate\Database\Query\Processors\Processor;

class SnowflakeProcessor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result) {
            return ((object) $result)->column_name;
        }, $results);
    }
}
