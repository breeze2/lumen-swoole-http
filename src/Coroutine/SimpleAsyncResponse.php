<?php

namespace BL\SwooleHttp\Coroutine;

use BL\SwooleHttp\Database\SlowQuery;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;
use swoole_mysql as SwooleMySQL;

class SimpleAsyncResponse
{
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
        // if ($current_value instanceof SlowQuery) {
        //     $type = 1;
        //     $sql = $current_value->getSql();

        //     // $last_generator->send(1);
        //     // if (!$last_generator->valid()) {
        //     //     $final_value = $last_generator->getReturn();
        //     //     $final_value = $scheduler->fullRun($final_value);
        //     //     $this->generator->send($final_value);
        //     //     if (!$this->generator->valid()) {
        //     //         $final_value = $this->generator->getReturn();
        //     //         $response->end(1111);
        //     //         var_dump($worker->coroutineNum++);
        //     //     }
        //     // }
        // }

        if ($type) {
            $db     = new SwooleMySQL();
            $config = $worker->mysqlReadConfig;

            $caller = $this;
            $db->connect($config, function ($db, $result) use ($request, $response, $worker, $last_generator, $type, $sql, $caller) {
                $caller->runAsyncSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql, $db);
            });
        } else {
            return $this->runNormalTask($request, $response, $worker, $last_generator);
        }

    }

    public function runAsyncSlowQueryTask(SwooleHttpRequest $request, SwooleHttpResponse $response, $worker, $last_generator, $type, $sql, $db)
    {
        if ($type) {
            $caller = $this;
            var_dump($sql);
            $db->query($sql, function ($db, $result) use ($request, $response, $worker, $last_generator, $type, $sql, $caller) {
                $data = json_decode(json_encode($result));
                if ($type === 1) {
                    $value = $data;
                }
                var_dump($data);
                $last_generator->send($value);
                // if($last_generator->valid()) {
                //     $parse = $this->parseValueType($current_value);
                //              $type = $parse['type'];
                //              $sql = $parse['sql'];
                //              $this->runAsyncSlowQueryTask($request, $response, $worker, $last_generator, $type, $sql, $db);
                // } else {
                //     $db->close();
                //     $final_value = $caller->scheduler->fullRun($last_generator->getReturn());
                //     $this->generator->valid() && $this->generator->send($final_value);
                //     $http_response = $this->generator->getReturn();
                //     $worker->directLumenResponse($request, $response, $http_response);
                // }
                $caller->inAsyncSlowQueryTaskLoop($request, $response, $worker, $last_generator, $db);
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
        }
    }

    public function runSlowQueryTask()
    {

    }

    public function parseValueType($value)
    {
        $type = 0;
        $sql  = '';
        if ($value instanceof SlowQuery) {
            $type = 1;
            $sql  = $value->getRealSql();
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
}
