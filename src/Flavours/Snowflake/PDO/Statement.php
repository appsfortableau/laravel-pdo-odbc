<?php

namespace LaravelPdoOdbc\Flavours\Snowflake\PDO;

use PDO;
use PDOStatement;
use function strlen;
use function is_float;
use function func_get_args;
use function function_exists;
use const FILTER_VALIDATE_BOOLEAN;
use function call_user_func_array;

if (! function_exists('str_replace_first')) {
    function str_replace_first($search, $replace, $subject)
    {
        $pos = strpos($subject, $search);
        if (false !== $pos) {
            return substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }
}

class Statement extends PDOStatement
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

    public function execute($bound_input_params = null)
    {
        $query = $this->queryString;

        foreach ($this->bindings as $key => $param) {
            list($val, $type) = $param;

            // cast type
            if (is_float($val)) {
                $val = (float) $val;
            } elseif (PDO::PARAM_INT === $type || is_numeric($val)) {
                $val = (int) $val;
            } elseif (PDO::PARAM_BOOL === $type) {
                $val = (bool) filter_var($val, FILTER_VALIDATE_BOOLEAN);
            } elseif (PDO::PARAM_NULL === $type) {
                $val = 'null';
            } else {
                $val = "'".$val."'";
            }

            if (is_numeric($key)) {
                $query = str_replace_first('?', $val, $query);

                continue;
            }

            $query = str_replace($key, $val, $query);
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

    protected function replaceBindings(string &$query, array $bindings = []): string
    {
        return $query;
    }
}
