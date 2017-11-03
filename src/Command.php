<?php
namespace BL\SwooleHttp;

class Command
{
    const VERSION = 'lumen-swoole-http 0.1.0';

    

    public static function main($argv)
    {
        $command = new static();
        return $command->run($argv);
    }

    protected function run($argv)
    {
        switch ($argv[1]) {
            case 'start':

                break;

            default:
                # code...
                break;
        }
    }

    protected function getPid()
    {
        $pid_file = getPidFile();
        if (file_exists($pid_file)) {
            $pid = file_get_contents($pid_file);
            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pid_file);
            }
        }
        return false;
    }
}
