<?php

namespace BL\SwooleHttp\Coroutine\Traits;

use BL\SwooleHttp\Coroutine\SimpleAsyncResponse;
use BL\SwooleHttp\Database\EloquentBuilder;
use BL\SwooleHttp\Database\SlowQuery;
use BL\SwooleHttp\Service;
use ErrorException;
use Generator;
use swoole_http_request as SwooleHttpRequest;
use swoole_http_response as SwooleHttpResponse;

trait SimpleAsyncMySQL
{

    abstract public function getYieldType();
    abstract public function dbErrorHandle();
    abstract public function appErrorHandle();
    public function runAsyncMySQLTask(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator, $db)
    {
        $value         = null;
        $current_value = $last_generator->current();
        $sql           = $this->getYieldSQL($current_value);
        $type          = $this->getYieldType($current_value);
        $caller        = $this;

        if($sql===false) {
            $value = $last_generator->current();
            try {
                $last_generator->send($value);
                $caller->inAsyncMySQLTaskLoop($request, $response, $worker, $last_generator, $db);
            } catch (ErrorException $e) {
                $caller->appErrorHandle($request, $response, $worker, $e);
            }
            return;
        }
        $db->query($sql, function ($db, $result) use ($request, $response, $worker, $last_generator, $type, $sql, $caller) {
            if ($result === false) {
                $e = new ErrorException(sprintf("Async DB: %s %s", $db->errno, $db->error));
                $caller->dbErrorHandle($request, $response, $worker, $e);
                $db->close();
                return;
            }

            $data = json_decode(json_encode($result));
            if ($type === SimpleAsyncResponse::YIELD_TYPE_SLOWQUERY) {
                $value = $data;
            } else if ($type === SimpleAsyncResponse::YIELD_TYPE_QUERYBUILDER) {
                $value = $last_generator->current()->yieldCollect($data);
            } else {
                $value = $last_generator->current();
            }
            try {
                $last_generator->send($value);
                $caller->inAsyncMySQLTaskLoop($request, $response, $worker, $last_generator, $db);
            } catch (ErrorException $e) {
                $caller->appErrorHandle($request, $response, $worker, $e);
            }
        });

    }

    public function inAsyncMySQLTaskLoop(SwooleHttpRequest $request, SwooleHttpResponse $response, Service $worker, $last_generator, $db)
    {
        if ($last_generator->valid()) {
            // $current_value = $last_generator->current();
            $this->runAsyncMySQLTask($request, $response, $worker, $last_generator, $db);
        } else {
            $db->close();
            $final_value = $this->scheduler->fullRun($last_generator->getReturn());
            $this->generator->valid() && $this->generator->send($final_value);
            $http_response = $this->generator->getReturn();
            $worker->directLumenResponse($request, $response, $http_response);
            $worker->downCoroutineNum();
            $this->activated = 0;
        }
    }

    public function getYieldSQL($yield)
    {
        $sql = false;
        if ($yield instanceof SlowQuery) {
            $sql = $yield->getRealSql();
        } else if ($yield instanceof EloquentBuilder) {
            $sql = $yield->getQuery()->getRealSql();
        } else {
            $sql = false;
        }
        return $sql;
    }

}
