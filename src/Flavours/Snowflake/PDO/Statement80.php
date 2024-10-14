<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\PDO;

use PDO;
use PDOStatement;

use function call_user_func_array;
use function func_get_args;
use function is_float;

use const FILTER_VALIDATE_BOOLEAN;

class Statement80 extends PDOStatement
{
    protected $pdo = null;

    protected $exec = null;

    protected $bindings = [];

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // TODO: Check when using pdo_snowflake can we use the default again?
    public function bindValue($parameter, $value, $type = null): bool
    {
        $type = null === $value ? PDO::PARAM_NULL : $type;
        $this->bindings[$parameter] = [$value, $type];

        return true;
    }

    // TODO: Check when using pdo_snowflake can we use the default again?
    public function bindParam($parameter, &$value, $type = null, $maxlen = null, $driverdata = null): bool
    {
        $this->bindings[$parameter] = [$value, $type];

        return true;
    }

    public function columnCount(): int
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    protected function _prepareValues(): array
    {
        $bindings = [];
        foreach ($this->bindings as $key => $param) {
            list($val, $type) = $param;

            // cast type
            if (is_float($val)) {
                $val = (float) $val;
            } elseif (PDO::PARAM_INT === $type) {
                $val = (int) $val;
            } elseif (PDO::PARAM_BOOL === $type) {
                $val = (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif (PDO::PARAM_NULL === $type) {
                $val = 'null';
            } else {
                $val = "'" . addslashes($val) . "'";
            }

            $bindings[$key] = $val;
        }

        return $bindings;
    }

    public function execute(?array $params = null): bool
    {
        $query = explode('?', $this->queryString);

        if (count($query) > 1) {
            $bindings = $this->_prepareValues();

            $buildQuery = '';
            for ($i = 0; $i < count($query); $i++) {
                $val = $bindings[$i] ?? '';
                $buildQuery .= ($val) . $query[$i];
            }
            $query = $buildQuery;
        } else {
            $query = reset($query);
        }

        // reset PDO Statement for "parent"
        $this->exec = $this->pdo->prepare($query, [PDO::ATTR_STATEMENT_CLASS => [PDOStatement::class]]);

        return $this->exec->execute($params);
    }

    public function fetch($how = null, $orientation = null, $offset = null): mixed
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchColumn($column_number = 0): mixed
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchObject($class_name = null, $ctor_args = null): object|false
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }
}
