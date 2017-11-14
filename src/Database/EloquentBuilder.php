<?php

namespace BL\SwooleHttp\Database;

use Illuminate\Database\Eloquent\Builder;

class EloquentBuilder extends Builder
{
    public function yieldCollect($data)
    {
        $builder = $this->applyScopes();

        $models = $this->model->hydrate(
            collect($data)->all()
        )->all();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }
        return $builder->getModel()->newCollection($models);
    }

    public function yieldGet($columns = ['*'])
    {
        $this->query->yieldGet($columns);
        return $this;
    }

}
