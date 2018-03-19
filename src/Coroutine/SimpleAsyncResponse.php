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
        $this->generator        = $gen;
        $this->activated        = 0;
        $this->xhgui_collecting = 1;
    }

    public function disableXhguiCollector(Service $worker)
    {
        if ($this->xhgui_collecting) {
            $this->xhgui_collecting = 0;
            $worker->xhguiCollector && $worker->xhguiCollector->collectorDisable();
        }
    }

    public function process(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker)
    {
        if (!$worker->xhguiCollector) {
            $this->xhgui_collecting = 0;
        }

        $scheduler = new SimpleSerialScheduler();
        $gen       = $this->generator;
        while ($gen instanceof Generator) {
            $scheduler->addTask($gen);
            $gen = $gen->current();
        }
        $last_task       = $scheduler->popTask();
        $current_value   = $gen;
        $last_generator  = $last_task->getGenerator();
        $this->scheduler = $scheduler;
        $type            = $this->getYieldType($current_value);
        if ($type === SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY || $type === SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER) {
            $this->disableXhguiCollector($worker);
            return $this->processSlowQuery($request, $response, $worker, $last_generator);
        } else if ($current_value instanceof CustomAsyncProcess) {
            $this->disableXhguiCollector($worker);
            return $this->processCustom($request, $response, $worker, $last_generator);
        } else {
            $this->processNormal($request, $response, $worker, $last_generator);
            $this->disableXhguiCollector($worker);
            return;
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

    public function processCustom(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        $current_value = $last_generator->current();
        if ($worker->canDoCoroutine()) {
            $worker->upCoroutineNum();
            return $current_value->runAsyncTask($request, $response, $worker, $this->scheduler, $last_generator);
        } else {
            return $current_value->runNormalTask($request, $response, $worker, $this->scheduler, $last_generator);
        }
    }

    public function processNormal(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        return $this->runNormalTask($request, $response, $worker, $last_generator);
    }

    public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator)
    {
        $this->scheduler->addTask($last_generator);
        $final_value   = $this->scheduler->fullRun($last_generator->current());
        $http_response = $final_value;
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
