<?php

namespace BL\SwooleHttp\Database;

use Illuminate\Database\Eloquent\Builder;

class EloquentBuilder extends Builder
{
    public function yieldCollect($data)
    {
        return $this->model->hydrate(
            collect($data)
        )->all();
    }
}
