<?php
namespace BL\SwooleHttp;

use Illuminate\Http\Request as IlluminateHttpRequest;
use Illuminate\Http\Response as IlluminateHttpResponse;
use swoole_http_server as SwooleHttpServer;

class Service
{

    protected $app;
    protected $pidFile;
    protected $server;
    // protected $host;
    // protected $port;
    // protected $settings;

    public function __construct(Application $app, $config)
    {
        $this->app = $app;
        $this->pidFile = $config['pid_file'];
        $this->server  = new SwooleHttpServer($config['host'], $config['port']);
        if (isset($config['swoole_settings']) && !empty($config['swoole_settings'])) {
            $this->server->set($config['swoole_settings']);
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
        file_put_contents(
            $this->pidFile,
            $serv->master_pid
        );
    }

    public function onShutdown()
    {
        unlink($this->pidFile);
    }

    public function onWorkerStart($serv, $worker_id)
    {

    }

    public function onRequest($request, $response)
    {
        try {
            $http_request = $this->parseRequest($request);
            $http_response = $this->app->dispatch($http_request);

            // Is gzip enabled and the client accept it?
            $accept_gzip = 1 && isset($request->header['accept-encoding']) && stripos($request->header['accept-encoding'], 'gzip') !== false;

            if ($http_response instanceof IlluminateHttpResponse) {
                $this->makeResponse($response, $http_response, $accept_gzip);
            } else {
                //echo (string) $response;
                $response->end((string)$http_response);
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
        $get = isset($request->get) ? $request->get : array();
        $post = isset($request->post) ? $request->post : array();
        $cookie = isset($request->cookie) ? $request->cookie : array();
        $server = isset($request->server) ? $request->server : array();
        $header = isset($request->header) ? $request->header : array();
        $files = isset($request->files) ? $request->files : array();
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
        // $_SERVER = array_merge($this->_SERVER, $new_server);

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
        if($accept_gzip && isset($response->header['Content-Type'])) {
            $mime = $response->header['Content-Type'];
            if(strlen($content) > $this->gzip_min_length && is_mime_gzip($mime)) {
                $response->gzip($this->gzip);
            }
        }

        // send content & close
        $response->end($content);
    }

}
