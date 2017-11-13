<?php

namespace BL\SwooleHttp\Database;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new Builder(
            $connection, $connection->getQueryGrammar(), $connection->getPostProcessor()
        );
    }
}
