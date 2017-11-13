<?php

namespace BL\SwooleHttp\Database;

use DateTimeInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Builder extends QueryBuilder
{
    public $dateFormat = 'Y-m-d H:i:s';

    public function yieldGet()
    {
        return $this;
    }

    public function getRealSql()
    {
        $sql    = $this->toSql();
        $params = $this->getBindings();
        $params = $this->yieldPrepareBindings($params);
        return $this->bindSqlParams($sql, $params);
    }

    public function yieldPrepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            // We need to transform all instances of DateTimeInterface into the actual
            // date string. Each query grammar maintains its own date string format
            // so we'll just ask the grammar for the format to get from the date.
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($this->dateFormat);
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    public function bindSqlParams($sql, $params)
    {
        $off = 0;
        $pos = 0;
        $num = 0;
        while ($pos = strpos($sql, ' ?', $off)) {
            $val = $params[$num++];
            if (is_int($val) || is_double($val) || is_float($val)) {
                $val = (string) $val;
            } else {
                $val = '"' . $val . '"';
            }
            $sql = substr_replace($sql, ' ' . $val, $pos, 2);
            $off = $pos + strlen($val) + 1 - 2;
        }
        return $sql;
    }
}
