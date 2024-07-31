<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\PDO;

use PDO;
use PDOStatement;

use function call_user_func_array;
use function func_get_args;
use function is_float;

use const FILTER_VALIDATE_BOOLEAN;

// Everything before PHP 8.0; Statement implementation
class Statement74 extends PDOStatement
{
    protected $pdo = null;

    protected $exec = null;

    protected $bindings = [];

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function bindValue($parameter, $value, $type = null)
    {
        $type = null === $value ? PDO::PARAM_NULL : $type;
        $this->bindings[$parameter] = [$value, $type];

        return $this;
    }

    public function bindParam($parameter, &$value, $type = null, $maxlen = null, $driverdata = null)
    {
        $this->bindings[$parameter] = [$value, $type];

        return $this;
    }

    public function columnCount()
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    protected function _prepareValues(): array
    {
        // Workaround for ODBC be broken for non-binded queries.
        if (count($this->bindings) === 0) {
            return parent::execute($bound_input_params);
        }

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

    public function execute($bound_input_params = null)
    {
        // TEMP: all adding constraints queries are failing, current workaround.
        if (str_contains($this->queryString, 'add constraint')) {
            return true;
        }

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

        return $this->exec->execute($bound_input_params);
    }

    public function fetch($how = null, $orientation = null, $offset = null)
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchAll($how = PDO::FETCH_BOTH, $class_name = null, $ctor_args = null)
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchColumn($column_number = 0)
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }

    public function fetchObject($class_name = null, $ctor_args = null)
    {
        if ($this->exec) {
            return call_user_func_array([$this->exec, __FUNCTION__], func_get_args());
        }

        return call_user_func_array([$this, __FUNCTION__], func_get_args());
    }
}
