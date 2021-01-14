<?php

namespace LaravelPdoOdbc\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class SnowflakeProcessor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param array $results
     *
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result) {
            return ((object) $result)->column_name;
        }, $results);
    }

    /**
     * Process an "insert get ID" query.
     *
     * @param string      $sql
     * @param array       $values
     * @param string|null $sequence
     *
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();

        $connection->insert($sql, $values);

        $result = $connection->selectOne('select * from '.$query->connection->wrapTable($query->from).' at(statement=>last_query_id())');
        // hacky.... TODO we should fix this proper way...
        $id = array_values((array) $result)[0];

        return is_numeric($id) ? (int) $id : $id;
    }
}
