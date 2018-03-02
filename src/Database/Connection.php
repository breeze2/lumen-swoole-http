<?php

namespace BL\SwooleHttp\Database;

class Connection
{
    const DEFAULT_TIMEOUT = 15;

    public static function getMySQLReadConfig()
    {
        $config = config('database.connections.mysql');
        if (isset($config['read'])) {
            return array(
                'host'        => isset($config['read']['host']) ? $config['read']['host'] : $config['host'],
                'port'        => isset($config['read']['port']) ? $config['read']['port'] : $config['port'],
                'user'        => isset($config['read']['username']) ? $config['read']['username'] : $config['username'],
                'password'    => isset($config['read']['password']) ? $config['read']['password'] : $config['password'],
                'database'    => isset($config['read']['database']) ? $config['read']['database'] : $config['database'],
                'charset'     => isset($config['read']['charset']) ? $config['read']['charset'] : $config['charset'],
                'strict_type' => isset($config['read']['strict']) ? $config['read']['strict'] : $config['strict'],
                'timeout'     => isset($config['read']['timeout']) ? $config['read']['timeout'] : $config['timeout'],
            );
        }

        return array(
            'host'     => $config['host'],
            'port'     => $config['port'],
            'user'     => $config['username'],
            'password' => $config['password'],
            'database' => $config['database'],
            'charset'  => $config['charset'],
            'timeout'  => $config['charset'] ? $config['charset'] : self::DEFAULT_TIMEOUT,
        );
    }

    public static function getConnectConfig($driver = 'mysql')
    {

        switch ($driver) {
            case 'mysql':
                return self::getMySQLReadConfig();
                break;

            default:
                return [];
                break;
        }
    }
}
