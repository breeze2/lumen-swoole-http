<?php

namespace BL\SwooleHttp\Database;

class SlowQuery
{
    public $driver = '';

    public $sql = '';

    public function __construct($sql, $driver = 'mysql')
    {
        $this->sql    = $sql;
        $this->driver = $driver;
    }

    public function getRealSql()
    {
        return $this->sql;
    }

}
