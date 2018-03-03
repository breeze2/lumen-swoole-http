<?php

namespace BL\SwooleHttp\Coroutine;

use BL\SwooleHttp\Database\EloquentBuilder;
use BL\SwooleHttp\Database\SlowQuery;
use BL\SwooleHttp\Service;
use ErrorException;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;
use swoole_mysql as SwooleMySQL;

class SimpleAsyncResponse
{
    const YIELD_TYPE_SLOWQUERY    = 1;
    const YIELD_TYPE_QUERYBUILDER = 2;

    protected $generator;
    protected $scheduler;
    protected $activated;

    use Traits\SimpleNormalMySQL;
    use Traits\SimpleAsyncMySQL;

    public function __construct(Generator $gen)
    {
        $this->generator = $gen;
        $this->activated = 0;
    }

    public function process(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker)
    {
        $temp1     = $this->generator->current();
        $temp2     = null;
        $scheduler = new SimpleSerialScheduler();
        while ($temp1 instanceof Generator) {
            $temp2 = $temp1->current();
            if ($temp2 instanceof Generator) {
                $scheduler->addTask($temp1);
                $temp1 = $temp2;
            } else {
                break;
            }
        }
        $last_generator  = $temp1 instanceof Generator ? $temp1 : $this->generator;
        $current_value   = $temp1 instanceof Generator ? $temp2 : $temp1;
        $this->scheduler = $scheduler;

        $type = $this->getYieldType($current_value);
        if ($type === SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY || $type === SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER) {
            return $this->processSlowQuery($request, $response, $worker, $last_generator);
        } else {
            return $this->processNormal($request, $response, $worker, $last_generator);
        }

    }

    public function processSlowQuery(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        if ($worker->canDoCoroutine()) {
            $worker->upCoroutineNum();
            $this->activated = 1;
            $db              = new SwooleMySQL();
            $config          = $worker->mysqlReadConfig;
            $caller          = $this;
            return $db->connect($config, function ($db, $result) use ($request, $response, $worker, $last_generator, $caller) {
                if ($db->connect_errno == 110) {
                    $worker->downCoroutineNum();
                    $caller->activated = 0;
                    return $caller->runNormalTask($request, $response, $worker, $last_generator);
                }
                if ($result === false) {
                    $e = new ErrorException(sprintf("Async DB: %s %s", $db->connect_errno, $db->connect_error));
                    $caller->dbErrorHandle($request, $response, $worker, $e);
                    return;
                }

                $caller->runAsyncMySQLTask($request, $response, $worker, $last_generator, $db);
            });
            // return $this->runAsyncMySQLTask($request, $response, $worker, $last_generator);
        } else {
            return $this->runNormalMySQLTask($request, $response, $worker, $last_generator);
        }
    }

    public function processNormal(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        return $this->runNormalTask($request, $response, $worker, $last_generator);
    }

    public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        $this->scheduler->addTask($last_generator);
        $final_value = $this->scheduler->fullRun($last_generator->current());
        $this->generator->valid() && $this->generator->send($final_value);
        $http_response = $this->generator->getReturn();
        $worker->directLumenResponse($request, $response, $http_response);
    }

    public function getYieldType($yield)
    {
        $type = 0;
        if ($yield instanceof SlowQuery) {
            $type = SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY;
        } else if ($yield instanceof EloquentBuilder) {
            $type = SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER;
        } else {
            $type = 0;
        }
        return $type;
    }

    public function appErrorHandle(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, ErrorException $e)
    {
        if ($this->activated) {
            $worker->downCoroutineNum();
        }

        $worker->logServerError($e);
        $response->status(500);
        $response->end('check server logs to get more info!');
        $worker->logHttpRequest($request, 500);
        $this->activated = null;
        $this->generator = null;
        $this->scheduler = null;
    }

    public function dbErrorHandle(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, ErrorException $e)
    {
        if ($this->activated) {
            $worker->downCoroutineNum();
        }

        $worker->logServerError($e);
        $response->status(500);
        $response->end('check server logs to get more info!');
        $worker->logHttpRequest($request, 500);
        $this->activated = null;
        $this->generator = null;
        $this->scheduler = null;
    }
}
