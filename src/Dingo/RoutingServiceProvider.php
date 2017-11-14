<?php
namespace BL\SwooleHttp\Dingo;

use Dingo\Api\Contract\Debug\ExceptionHandler;
use Dingo\Api\Contract\Routing\Adapter;
use Dingo\Api\Provider\RoutingServiceProvider as DingoRoutingServiceProvider;
use Dingo\Api\Routing\ResourceRegistrar;

class RoutingServiceProvider extends DingoRoutingServiceProvider
{
    /**
     * Register the router.
     */
    protected function registerRouter()
    {
        $this->app->singleton('api.router', function ($app) {
            $router = new Router(
                $app[Adapter::class],
                $app[ExceptionHandler::class],
                $app,
                $this->config('domain'),
                $this->config('prefix')
            );

            $router->setConditionalRequest($this->config('conditionalRequest'));

            return $router;
        });

        $this->app->singleton(ResourceRegistrar::class, function ($app) {
            return new ResourceRegistrar($app[Router::class]);
        });
    }
}
