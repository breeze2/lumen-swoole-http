<?php
namespace BL\SwooleHttp;

class Command
{
    const VERSION = 'lumen-swoole-http 0.1.0';
    const CONFIG_PREFIX = 'SWOOLE_HTTP_';

    protected $lumen;
    protected $config;
    protected $pidFile;
    protected $service;

    private function __construct()
    {
        $this->lumen = require dirname(LUMENSWOOLEHTTP_COMPOSER_INSTALL) . '/../bootstrap/swoole.php';
        $this->config = $this->initializeConfig();
        $this->pidFile = $this->config['pid_file'];

    }

    private function initializeConfig()
    {
        $params = array(
            'reactor_num',
            'worker_num',
            'max_request',
            // 'max_conn',
            'task_worker_num',
            'task_ipc_mode',
            'task_max_request',
            'task_tmpdir',
            'dispatch_mode',
            'dispatch_func',
            'message_queue_key',
            // 'daemonize',
            'backlog',
            // 'log_file',
            'log_level',
            'heartbeat_check_interval',
            'heartbeat_idle_time',
            'open_eof_check',
            'open_eof_split',
            'package_eof',
            'open_length_check',
            'package_length_type',
            'package_length_func',
            'package_max_length',
            'open_cpu_affinity',
            'cpu_affinity_ignore',
            'open_tcp_nodelay',
            'tcp_defer_accept',
            'ssl_cert_file',
            'ssl_method',
            'ssl_ciphers',
            'user',
            'group',
            'chroot',
            'pid_file',
            'pipe_buffer_size',
            'buffer_output_size',
            'socket_buffer_size',
            'enable_unsafe_event',
            'discard_timeout_request',
            'enable_reuse_port',
            'ssl_ciphers',
            'enable_delay_receive',
            'open_http_protocol',
            'open_http2_protocol',
            'open_websocket_protocol',
            'open_mqtt_protocol',
            'reload_async',
            'tcp_fastopen',
        );
        $settings = array();
        foreach ($params as $param) {
            $key = self::CONFIG_PREFIX . strtoupper($param);
            $value = env($key);
            if ($value !== null) {
                $settings[$param] = $value;
            }
        }

        $config['worker_num'] = env(self::CONFIG_PREFIX . 'WORK_NUM', 1);
        $config['max_conn'] = env(self::CONFIG_PREFIX . 'MAX_CONN', 2000);
        $config['daemonize'] = env(self::CONFIG_PREFIX . 'DAEMONIZE', false);
        $config['log_file'] = env(self::CONFIG_PREFIX . 'LOG_FILE', storage_path('logs/swoole-http.log'));


        $config['swoole_settings'] = $settings;
        $config['host'] = env(self::CONFIG_PREFIX . 'HOST', '127.0.0.1');
        $config['port'] = env(self::CONFIG_PREFIX . 'PORT', 9050);
        $config['gzip'] = env(self::CONFIG_PREFIX . 'GZIP', true);
        $config['gzip_min_length'] = env(self::CONFIG_PREFIX . 'GZIP_MIN_LENGTH', 1024);
        $config['deal_with_public'] = env(self::CONFIG_PREFIX . 'DEAL_WITH_PUBLIC', false);
        $config['pid_file'] = env(self::CONFIG_PREFIX . 'PID_FILE', storage_path('app/swoole-http.pid'));
        $config['root_dir'] = base_path();
        return $config;
    }

    protected function run($argv)
    {
        switch ($argv[1]) {
            case 'start':
                $this->startService();
                break;

            case 'stop':
                $this->stopService();
            
            case 'restart':
                $this->restartService();
            
            case 'reload':
                $this->reloadService();

            default:
                # code...
                break;
        }
    }

    protected function startService()
    {
        if ($this->getPid()) {
            echo 'umen-swoole-http is already running' . PHP_EOL;
            exit(1);
        }

        $service = new Service($this->lumen, $this->config);
        $service->start();
    }

    protected function restartService()
    {
        echo storage_path('app');
    }

    protected function reloadService()
    {
        echo storage_path('app');
    }

    protected function stopService()
    {
        echo storage_path('app');
    }

    protected function sendSignal($signal)
    {
        if ($pid = $this->getPid()) {
            posix_kill($pid, $sig);
        } else {
            echo "umen-swoole-http is not running!" . PHP_EOL;
            exit(1);
        }
    }

    protected function getPid()
    {
        if ($this->pidFile && file_exists($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($this->pidFile);
            }
        }
        return false;
    }

    public static function main($argv)
    {
        $command = new static;
        return $command->run($argv);
    }
}
