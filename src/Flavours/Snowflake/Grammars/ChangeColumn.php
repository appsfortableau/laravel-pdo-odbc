<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\Grammars;

use RuntimeException;
use Illuminate\Support\Fluent;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;

/**
 * Changing actions:
 * - Add column
 * - Delete column
 * - Rename column
 * - Change column type
 *     - type change
 *     - precision change
 *     - null to not null
 *     - not null to null.
 */
class ChangeColumn
{
    /**
     * Compile a change column command into a series of SQL statements.
     *
     * @throws RuntimeException
     *
     * @return array
     */
    public static function compile(Grammar $grammar, Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $type = $command->offsetGet('name'); // can be: change, dropColumn, renameColumn

        if ('dropColumn' === $type) {
            return $grammar->compileDropColumn($blueprint, $command);
        } elseif ('renameColumn' === $type) {
            return $grammar->compileRenameColumn($blueprint, $command, $connection);
        }

        return $grammar->compileChangeColumn($blueprint, $command);
    }
}
