<?php

namespace BL\SwooleHttp\Coroutine;

use BL\SwooleHttp\Service;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;

abstract class CustomAsyncProcess
{
    abstract public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator);
    abstract public function runAsyncTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, SimpleSerialScheduler $scheduler, Generator $last_generator);

    protected function makeNormalResponse(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $http_response)
    {
        $worker->directLumenResponse($request, $response, $http_response);
    }

    protected function makeAsyncResponse(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $http_response)
    {
        $worker->directLumenResponse($request, $response, $http_response);
        $worker->downCoroutineNum();
    }

    protected function fullRunScheduler(SimpleSerialScheduler $scheduler, $last_generator, $value)
    {
        if ($last_generator instanceof Generator) {
            $scheduler->addTask($last_generator);
        } else {
            $value = $last_generator;
        }
        return $scheduler->fullRun($value);
    }
}
