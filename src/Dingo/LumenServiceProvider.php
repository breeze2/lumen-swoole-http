<?php
namespace BL\SwooleHttp\Dingo;

use Dingo\Api\Auth\Auth;
use Dingo\Api\Console\Command;
use Dingo\Api\Dispatcher;
use Dingo\Api\Provider\LumenServiceProvider as DingoLumenServiceProvider;
use Dingo\Api\Routing\Adapter\Lumen as LumenAdapter;
use FastRoute\DataGenerator\GroupCountBased as GcbDataGenerator;
use FastRoute\RouteParser\Std as StdRouteParser;

class LumenServiceProvider extends DingoLumenServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();

        $this->registerClassAliases();

        $this->app->register(RoutingServiceProvider::class);

        $this->app->register(HttpServiceProvider::class);

        $this->registerExceptionHandler();

        $this->registerDispatcher();

        $this->registerAuth();

        $this->registerTransformer();

        $this->registerDocsCommand();

        if (class_exists('Illuminate\Foundation\Application', false)) {
            $this->commands([
                \Dingo\Api\Console\Command\Cache::class,
                \Dingo\Api\Console\Command\Routes::class,
            ]);
        }

        $this->app->singleton('api.router.adapter', function ($app) {
            return new LumenAdapter($app, new StdRouteParser, new GcbDataGenerator, $this->getDispatcherResolver());
        });
    }

    /**
     * Register the class aliases.
     *
     * @return void
     */
    protected function registerClassAliases()
    {
        $aliases = [
            \Dingo\Api\Http\Request::class => \Dingo\Api\Contract\Http\Request::class,
            'api.dispatcher'               => \Dingo\Api\Dispatcher::class,
            'api.http.validator'           => \Dingo\Api\Http\RequestValidator::class,
            'api.http.response'            => \Dingo\Api\Http\Response\Factory::class,
            'api.router'                   => Router::class,
            'api.router.adapter'           => \Dingo\Api\Contract\Routing\Adapter::class,
            'api.auth'                     => \Dingo\Api\Auth\Auth::class,
            'api.limiting'                 => \Dingo\Api\Http\RateLimit\Handler::class,
            'api.transformer'              => \Dingo\Api\Transformer\Factory::class,
            'api.url'                      => \Dingo\Api\Routing\UrlGenerator::class,
            'api.exception'                => [\Dingo\Api\Exception\Handler::class, \Dingo\Api\Contract\Debug\ExceptionHandler::class],
        ];

        foreach ($aliases as $key => $aliases) {
            foreach ((array) $aliases as $alias) {
                $this->app->alias($key, $alias);
            }
        }
    }

    /**
     * Register the internal dispatcher.
     *
     * @return void
     */
    public function registerDispatcher()
    {
        $this->app->singleton('api.dispatcher', function ($app) {
            $dispatcher = new Dispatcher($app, $app['files'], $app[Router::class], $app[\Dingo\Api\Auth\Auth::class]);

            $dispatcher->setSubtype($this->config('subtype'));
            $dispatcher->setStandardsTree($this->config('standardsTree'));
            $dispatcher->setPrefix($this->config('prefix'));
            $dispatcher->setDefaultVersion($this->config('version'));
            $dispatcher->setDefaultDomain($this->config('domain'));
            $dispatcher->setDefaultFormat($this->config('defaultFormat'));

            return $dispatcher;
        });
    }

    /**
     * Register the auth.
     *
     * @return void
     */
    protected function registerAuth()
    {
        $this->app->singleton('api.auth', function ($app) {
            return new Auth($app[Router::class], $app, $this->config('auth'));
        });
    }

    /**
     * Register the documentation command.
     *
     * @return void
     */
    protected function registerDocsCommand()
    {
        $this->app->singleton(\Dingo\Api\Console\Command\Docs::class, function ($app) {
            return new Command\Docs(
                $app[Router::class],
                $app[\Dingo\Blueprint\Blueprint::class],
                $app[\Dingo\Blueprint\Writer::class],
                $this->config('name'),
                $this->config('version')
            );
        });

        $this->commands([\Dingo\Api\Console\Command\Docs::class]);
    }
}
