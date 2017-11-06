<?php
namespace BL\SwooleHttp;

use Illuminate\Http\Request as IlluminateHttpRequest;
use Laravel\Lumen\Application;
use swoole_http_server as SwooleHttpServer;
use Symfony\Component\HttpFoundation\BinaryFileResponse as SymfonyBinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Service
{
    protected $app;
    protected $server;
    protected $root;
    protected $bootstrap;
    // protected $host;
    // protected $port;
    protected $config;
    protected $setting;

    public function __construct(Application $app, array $config, array $setting)
    {
        $this->app     = $app;
        $this->config  = $config;
        $this->setting = $setting;
        $this->server  = new SwooleHttpServer($config['host'], $config['port']);
        if (isset($setting) && !empty($setting)) {
            $this->server->set($setting);
        }
    }

    public function start()
    {
        $this->server->on('start', array($this, 'onStart'));
        $this->server->on('shutdown', array($this, 'onShutdown'));
        $this->server->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->server->on('request', array($this, 'onRequest'));

        // require __DIR__ . '/mime.php';

        $this->server->start();
    }

    public function onStart($serv)
    {
        $file = $this->config['pid_file'];
        file_put_contents($file, $serv->master_pid);
    }

    public function onShutdown()
    {
        $file = $this->config['pid_file'];
        unlink($file);
    }

    public function onWorkerStart($serv, $worker_id)
    {
        $this->reloadApplication();
    }

    public function onRequest($request, $response)
    {
        if ($this->config['stats'] && $request->server['request_uri'] === $this->config['stats_uri']) {
            if ($this->statsResource($request, $response)) {
                return;
            }
        }

        if ($this->config['static_resources']) {
            if ($this->staticResource($request, $response)) {
                return;
            }
        }
        try {
            $http_request  = $this->parseRequest($request);
            $http_response = $this->app->dispatch($http_request);

            if ($http_response instanceof SymfonyBinaryFileResponse) {
                $response->sendfile($http_response->getFile());

            } else if ($http_response instanceof SymfonyResponse) {
                // Is gzip enabled and the client accept it?
                $accept_gzip = $this->config['gzip'] > 0 && isset($request->header['accept-encoding']) && stripos($request->header['accept-encoding'], 'gzip') !== false;

                $this->makeResponse($response, $http_response, $accept_gzip);

            } else {
                $response->end((string) $http_response);

            }

        } catch (ErrorException $e) {
            if (strncmp($e->getMessage(), 'swoole_', 7) === 0) {
                fwrite(STDOUT, $e->getFile() . '(' . $e->getLine() . '): ' . $e->getMessage() . PHP_EOL);
            }
        } finally {
            if (count($this->app->getMiddleware()) > 0) {
                $this->app->callTerminableMiddleware($http_response);
            }
        }
    }

    protected function parseRequest($request)
    {
        $get     = isset($request->get) ? $request->get : array();
        $post    = isset($request->post) ? $request->post : array();
        $cookie  = isset($request->cookie) ? $request->cookie : array();
        $server  = isset($request->server) ? $request->server : array();
        $header  = isset($request->header) ? $request->header : array();
        $files   = isset($request->files) ? $request->files : array();
        $fastcgi = array();

        // merge headers into server which are filtered ed by swoole
        // make a new array when php 7 has different behavior on foreach
        $new_header = array();
        foreach ($header as $key => $value) {
            $new_header['http_' . $key] = $value;
        }
        $server = array_merge($server, $new_header);

        $new_server = array();
        // swoole has changed all keys to lower case
        foreach ($server as $key => $value) {
            $new_server[strtoupper($key)] = $value;
        }

        // override $_SERVER, for many packages use the raw variable
        // $_SERVER = $new_server;

        $content = $request->rawContent() ?: null;

        $http_request = new IlluminateHttpRequest($get, $post, $fastcgi, $cookie, $files, $new_server, $content);

        return $http_request;
    }

    protected function makeResponse($response, $http_response, $accept_gzip)
    {
        // status
        $response->status($http_response->getStatusCode());
        // headers
        foreach ($http_response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($http_response->headers->getCookies() as $cookie) {
            $response->rawcookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }
        // content
        $content = $http_response->getContent();

        // check gzip
        if ($accept_gzip && isset($response->header['Content-Type'])) {
            $mime = $response->header['Content-Type'];
            if (strlen($content) > $this->config['gzip_min_length'] && $this->checkGzipMime($mime)) {
                $response->gzip($this->config['gzip']);
            }
        }

        // send content & close
        $response->end($content);
    }

    protected function staticResource($request, $response)
    {
        $public_dir = $this->config['public_dir'];
        $uri        = $request->server['request_uri'];
        $file       = realpath($public_dir . $uri);
        if (is_file($file)) {
            if (!strncasecmp($file, $uri, strlen($public_dir))) {
                $response->status(403);
                $response->end();
            } else {
                $response->header('Content-Type', mime_content_type($file));
                $response->sendfile($file);
            }
            return true;

        }
        return false;
    }

    protected function statsResource($request, $response)
    {
        $stats = $this->server->stats();
        $response->end(json_encode($stats));
        return true;
    }

    protected function checkGzipMime($mime)
    {
        static $mimes = [
            'text/plain'             => true,
            'text/html'              => true,
            'text/css'               => true,
            'application/javascript' => true,
            'application/json'       => true,
            'application/xml'        => true,
        ];
        if ($pos = strpos($mime, ';')) {
            $mime = substr($mime, 0, $pos);
        }
        return isset($mimes[strtolower($mime)]);
    }

    protected function reloadApplication()
    {
        $root_dir  = $this->config['root_dir'];
        $bootstrap = $this->config['bootstrap'];
        require $root_dir . '/vendor/autoload.php';
        $this->app = require $bootstrap;
    }

}
