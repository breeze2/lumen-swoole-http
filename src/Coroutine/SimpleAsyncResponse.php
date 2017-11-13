<?php

namespace BL\SwooleHttp\Coroutine;

use BL\SwooleHttp\Database\SlowQuery;
use BL\SwooleHttp\Database\EloquentBuilder;
use ErrorException;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;
use swoole_mysql as SwooleMySQL;

class SimpleAsyncResponse
{
    const YIELD_TYPE_SLOWQUERY = 1;
    const YIELD_TYPE_QUERYBUILDER = 2;

    protected $generator;
    protected $scheduler;

    public function __construct(Generator $gen)
    {
        $this->generator = $gen;
    }

    public function process(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, callable $callback = null)
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
        $last_generator  = $temp1 instanceof Generator ? $temp1 : $this->generator();
        $current_value   = $temp1 instanceof Generator ? $temp2 : $temp1;
        $this->scheduler = $scheduler;

        $parse = $this->parseValueType($current_value);
        $type  = $parse['type'];
        $sql   = $parse['sql'];

        if ($type) {
            $db     = new SwooleMySQL();
            $config = $worker->mysqlReadConfig;

            $caller = $this;
            if ($worker->canDoCoroutine()) {
                $worker->upCoroutineNum();
                $db->connect($config, function ($db, $result) use ($request, $response, $worker, $last_generator, $type, $sql, $caller) {
                    $caller->runAsyncSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql, $db);
                });
            } else {
                return $this->runSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql);
            }

        } else {
            return $this->runNormalTask($request, $response, $worker, $last_generator);
        }

    }

    public function runAsyncSlowQueryTask(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator, $type, $sql, $db)
    {
        if ($type) {
            $caller = $this;
            $db->query($sql, function ($db, $result) use ($request, $response, $worker, $last_generator, $type, $sql, $caller) {
                $data = json_decode(json_encode($result));
                if ($type === SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY) {
                    $value = $data;
                } else if ($type === SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER) {
                    $value = $last_generator->current()->yieldCollect($data);
                    var_dump($value);
                }
                try {
                    $last_generator->send($value);
                    $caller->inAsyncSlowQueryTaskLoop($request, $response, $worker, $last_generator, $db);
                } catch (ErrorException $e) {
                    $caller->exceptionHandle($e, $response, $worker);
                }
            });
        } else {
            $last_generator->send($last_generator->current());
            $this->inAsyncSlowQueryTaskLoop($request, $response, $worker, $last_generator, $db);
        }

    }

    public function inAsyncSlowQueryTaskLoop(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator, $db)
    {
        if ($last_generator->valid()) {
            $parse = $this->parseValueType($current_value);
            $type  = $parse['type'];
            $sql   = $parse['sql'];
            $this->runAsyncSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql, $db);
        } else {
            $db->close();
            $final_value = $this->scheduler->fullRun($last_generator->getReturn());
            $this->generator->valid() && $this->generator->send($final_value);
            $http_response = $this->generator->getReturn();
            $worker->directLumenResponse($request, $response, $http_response);
            $worker->downCoroutineNum();
        }
    }

    public function runSlowQueryTask(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator, $type, $sql)
    {
        $value = null;
        if ($type === SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY) {
            $value = app('db')->select($sql);
        } else if ($type === SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER) {
            $current_value = $last_generator->current();
            $value = $current_value->get($current_value->getQuery()->getRealColumns());
        } else {
            $value = $last_generator->current();
        }
        try {
            $last_generator->send($value);
            $this->inSlowQueryTaskLoop($request, $response, $worker, $last_generator);
        } catch (ErrorException $e) {
            $this->exceptionHandle($e, $response, $worker);
        }

    }

    public function inSlowQueryTaskLoop(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator)
    {
        if ($last_generator->valid()) {
            $parse = $this->parseValueType($current_value);
            $type  = $parse['type'];
            $sql   = $parse['sql'];
            $this->runSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql);
        } else {
            $final_value = $this->scheduler->fullRun($last_generator->getReturn());
            $this->generator->valid() && $this->generator->send($final_value);
            $http_response = $this->generator->getReturn();
            $worker->directLumenResponse($request, $response, $http_response);
        }
    }

    public function parseValueType($value)
    {
        $type = 0;
        $sql  = '';
        if ($value instanceof SlowQuery) {
            $type = SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY;
            $sql  = $value->getRealSql();
        } else if ($value instanceof EloquentBuilder) {
            $type = SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER;
            $sql  = $value->getQuery()->getRealSql();
        }
        return array('type' => $type, 'sql' => $sql);
    }

    public function runNormalTask(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator)
    {
        $this->scheduler->addTask($last_generator);
        $final_value = $this->scheduler->fullRun($last_generator->current());
        $this->generator->valid() && $this->generator->send($final_value);
        $http_response = $this->generator->getReturn();
        $worker->directLumenResponse($request, $response, $http_response);
    }

    public function exceptionHandle(ErrorException $e, SwooleHttpResponse $response, $worker)
    {
        $worker->logServerError($e);
        $response->status(500);
        $response->end('check server logs to get more info!');
    }
}
