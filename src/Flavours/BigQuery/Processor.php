<?php

namespace LaravelPdoOdbc\Flavours\Snowflake;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class Processor extends BaseProcessor
{
    public static function wrapTable($tableName): string
    {
        if ($tableName instanceof Blueprint) {
            $tableName = $tableName->getTable();
        }

        // if (! env('SNOWFLAKE_COLUMNS_CASE_SENSITIVE', false)) {
        //     $tableName = Str::upper($tableName);
        // }

        return $tableName;
    }

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

        $wrappedTable = $query->getGrammar()->wrapTable($query->from);

        $result = $connection->selectOne('select * from '.$wrappedTable.' at(statement=>last_query_id())');
        // hacky.... TODO we should fix this proper way...
        $id = array_values((array) $result)[0];

        return is_numeric($id) ? (int) $id : $id;
    }
}
