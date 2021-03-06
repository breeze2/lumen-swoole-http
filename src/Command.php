<?php
namespace BL\SwooleHttp;

class Command
{
    const VERSION       = 'lumen-swoole-http 0.1.0';
    const CONFIG_PREFIX = 'SWOOLE_HTTP_';

    protected $lumen;
    protected $bootstrap;
    protected $config;
    protected $setting;
    protected $pidFile;
    protected $service;

    private function __construct()
    {
        $this->checkBootstrap();
        $this->lumen   = require $this->bootstrap;
        $this->config  = $this->initializeConfig();
        $this->setting = $this->initializeSetting();
        $this->pidFile = $this->config['pid_file'];

    }

    private function checkBootstrap($file = 'swoole.php')
    {
        $bootstrap_path = dirname(LUMENSWOOLEHTTP_COMPOSER_INSTALL) . '/../bootstrap/';
        $bootstrap_file = $bootstrap_path . $file;
        if (file_exists($bootstrap_file)) {
            $this->bootstrap = $bootstrap_file;
            return true;
        } else {
            echo 'please copy ' . realpath(dirname(LUMENSWOOLEHTTP_COMPOSER_INSTALL) . '/breeze2/lumen-swoole-http/bootstrap/') . $file . PHP_EOL;
            echo 'to ' . realpath($bootstrap_path) . '/' . PHP_EOL;
            exit(1);
        }
        return false;
    }

    private function initializeSetting()
    {
        $params = array(
            'reactor_num',
            // 'worker_num',
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
        $setting = array();
        foreach ($params as $param) {
            $key   = self::CONFIG_PREFIX . strtoupper($param);
            $value = env($key);
            if ($value !== null) {
                $setting[$param] = $value;
            }
        }

        $setting['worker_num'] = env(self::CONFIG_PREFIX . 'WORKER_NUM', 1);
        $setting['max_conn']   = env(self::CONFIG_PREFIX . 'MAX_CONNECTIOIN') ?: env(self::CONFIG_PREFIX . 'MAX_CONN', 255);
        $setting['daemonize']  = env(self::CONFIG_PREFIX . 'DAEMONIZE', true);
        $setting['log_file']   = env(self::CONFIG_PREFIX . 'LOG_FILE', storage_path('logs/swoole-http.log'));

        return $setting;
    }

    private function initializeConfig()
    {
        $config['host']             = env(self::CONFIG_PREFIX . 'HOST', '127.0.0.1');
        $config['port']             = env(self::CONFIG_PREFIX . 'PORT', 9080);
        $config['gzip']             = env(self::CONFIG_PREFIX . 'GZIP', 1);
        $config['gzip_min_length']  = env(self::CONFIG_PREFIX . 'GZIP_MIN_LENGTH', 1024);
        $config['static_resources'] = env(self::CONFIG_PREFIX . 'STATIC_RESOURCES', false);
        $config['pid_file']         = env(self::CONFIG_PREFIX . 'PID_FILE', storage_path('app/swoole-http.pid'));
        // $config['stats']            = env(self::CONFIG_PREFIX . 'STATS', true);
        $config['stats_uri']         = env(self::CONFIG_PREFIX . 'STATS_URI', '/swoole-http-stats');
        $config['request_log_path']  = realpath(env(self::CONFIG_PREFIX . 'REQUEST_LOG_PATH'));
        $config['root_dir']          = base_path();
        $config['public_dir']        = base_path('public');
        $config['bootstrap']         = $this->bootstrap;
        $config['max_coroutine']     = env(self::CONFIG_PREFIX . 'MAX_COROUTINE', 10);
        $config['xhgui_collect']     = env(self::CONFIG_PREFIX . 'XHGUI_COLLECT', false);
        $config['xhgui_config_path'] = base_path('config/xhgui.php');
        return $config;
    }

    protected function run($argv)
    {
        switch ($argv[1]) {
            case 'start':
                $this->startService();
                break;

            case 'status':
                $this->checkService();
                break;

            case 'stop':
                $this->stopService();
                break;

            case 'restart':
                $this->restartService();
                break;

            case 'reload':
                $this->reloadService();
                break;

            case 'auto-reload':
                $this->autoReloadService();
                break;

            default:
                echo 'lumen-swoole-http start | stop | restart | reload | status | auto-reload' . PHP_EOL;
                exit(1);
                break;
        }
    }

    protected function startService()
    {
        if ($this->getPid()) {
            echo 'lumen-swoole-http is already running' . PHP_EOL;
            exit(1);
        }

        $service = new Service($this->lumen, $this->config, $this->setting);
        $service->start();
    }

    protected function restartService()
    {
        $time = 0;
        $pid  = $this->getPid();
        $this->sendSignal(SIGTERM);
        while (posix_getpgid($pid) && $time <= 10) {
            sleep(1);
            $time++;
        }
        if ($time > 10 && posix_getpgid($pid)) {
            echo 'lumen-swoole-http stop timeout' . PHP_EOL;
            exit(1);
        }
        $this->startService();
    }

    protected function reloadService()
    {
        $this->sendSignal(SIGUSR1);
    }

    protected function autoReloadService()
    {
        $pid = $this->getPid();
        if ($pid) {
            $kit = new AutoReload($pid);
            $kit->watch(base_path());
            $kit->addFileType('.php');
            $kit->run();
        } else {
            echo 'lumen-swoole-http is not running!' . PHP_EOL;
            exit(1);
        }

    }

    protected function stopService()
    {
        $time = 0;
        $pid  = $this->getPid();
        $this->sendSignal(SIGTERM);
        while (posix_getpgid($pid) && $time <= 10) {
            sleep(1);
            $time++;
        }
        if ($time > 10 && posix_getpgid($pid)) {
            echo 'lumen-swoole-http stop timeout' . PHP_EOL;
            exit(1);
        }
        exit(0);
    }

    protected function checkService()
    {
        $pid = $this->getPid();
        if ($pid) {
            echo 'lumen-swoole-http is running!' . PHP_EOL;
        } else {
            echo 'lumen-swoole-http is not running!' . PHP_EOL;
        }
    }

    protected function sendSignal($signal)
    {
        if ($pid = $this->getPid()) {
            posix_kill($pid, $signal);
            return true;
        } else {
            echo 'lumen-swoole-http is not running!' . PHP_EOL;
            exit(1);
        }
        return false;
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
