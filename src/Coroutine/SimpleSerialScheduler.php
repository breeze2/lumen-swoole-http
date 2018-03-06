<?php

namespace BL\SwooleHttp\Coroutine;

use Generator;
use SplQueue;

class SimpleSerialScheduler
{
    protected $taskNum = 0;
    protected $taskMap = []; // taskId => task
    protected $taskQueue;

    public function __construct()
    {
        $this->taskQueue = new SplQueue();
    }

    public function addTask(Generator $generator)
    {
        $tid                 = ++$this->taskNum;
        $task                = new SimpleTask($tid, $generator);
        $this->taskMap[$tid] = $task;
        $this->pushTask($task);
        return $tid;
    }

    public function pushTask(SimpleTask $task)
    {
        $this->taskQueue->push($task);
    }

    public function popTask()
    {
        return $this->taskQueue->pop();
    }

    public function fullRun($first)
    {
        $value = $first;
        while (!$this->taskQueue->isEmpty()) {
            $task  = $this->popTask();
            $value = $task->sendValue($value);

            if ($task->isFinished()) {
                $value = $task->getFinalValue();
                unset($this->taskMap[$task->getId()]);
            } else {
                $this->pushTask($task);
            }
        }
        return $value;
    }
}
