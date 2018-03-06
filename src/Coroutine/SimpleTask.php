<?php

namespace BL\SwooleHttp\Coroutine;

use Generator;

class SimpleTask
{
    protected $id;
    protected $generator;

    public function __construct($id, Generator $generator)
    {
        $this->id        = $id;
        $this->generator = $generator;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getGenerator()
    {
        return $this->generator;
    }

    public function sendValue($value)
    {
        return $this->generator->send($value);
    }

    public function isFinished()
    {
        return !$this->generator->valid();
    }

    public function getFinalValue()
    {
        return $this->generator->getReturn();
    }
}
