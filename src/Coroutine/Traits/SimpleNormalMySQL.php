<?php

namespace BL\SwooleHttp\Coroutine\Traits;

use BL\SwooleHttp\Database\EloquentBuilder;
use BL\SwooleHttp\Database\SlowQuery;
use BL\SwooleHttp\Service;
use ErrorException;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;

trait SimpleNormalMySQL
{
    abstract public function appErrorHandle();
    public function runNormalMySQLTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        $value         = null;
        $current_value = $last_generator->current();
        if ($current_value instanceof SlowQuery) {
            $sql   = $current_value->getRealSql();
            $value = app('db')->select($sql);
        } else if ($current_value instanceof EloquentBuilder) {
            $value = $current_value->get($current_value->getQuery()->getRealColumns());
        } else {
            $value = $last_generator->current();
        }

        try {
            $last_generator->send($value);
            $this->inNormalMySQLTaskLoop($request, $response, $worker, $last_generator);
        } catch (ErrorException $e) {
            $this->appErrorHandle($request, $response, $worker, $e);
        }

    }

    public function inNormalMySQLTaskLoop(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        if ($last_generator->valid()) {
            $this->runNormalMySQLTask($request, $response, $worker, $last_generator);
        } else {
            $final_value   = $this->scheduler->fullRun($last_generator->getReturn());
            $http_response = $final_value;
            $worker->directLumenResponse($request, $response, $http_response);
        }
    }

}
