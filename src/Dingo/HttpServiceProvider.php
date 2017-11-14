<?php
namespace BL\SwooleHttp\Dingo;

use Dingo\Api\Auth\Auth;
use Dingo\Api\Contract\Debug\ExceptionHandler;
use Dingo\Api\Http\Middleware;
use Dingo\Api\Http\Middleware\Auth as AuthMiddleware;
use Dingo\Api\Http\Middleware\PrepareController;
use Dingo\Api\Http\Middleware\RateLimit;
use Dingo\Api\Http\Middleware\Request;
use Dingo\Api\Http\RateLimit\Handler;
use Dingo\Api\Http\RequestValidator;
use Dingo\Api\Provider\HttpServiceProvider as DingoHttpServiceProvider;

class HttpServiceProvider extends DingoHttpServiceProvider
{
    /**
     * Register the middleware.
     *
     * @return void
     */
    protected function registerMiddleware()
    {
        $this->app->singleton(Request::class, function ($app) {
            $middleware = new Middleware\Request(
                $app,
                $app[ExceptionHandler::class],
                $app[Router::class],
                $app[RequestValidator::class],
                $app['events']
            );

            $middleware->setMiddlewares($this->config('middleware', false));

            return $middleware;
        });

        $this->app->singleton(AuthMiddleware::class, function ($app) {
            return new Middleware\Auth($app[Router::class], $app[Auth::class]);
        });

        $this->app->singleton(RateLimit::class, function ($app) {
            return new Middleware\RateLimit($app[Router::class], $app[Handler::class]);
        });

        $this->app->singleton(PrepareController::class, function ($app) {
            return new Middleware\PrepareController($app[Router::class]);
        });
    }
}
