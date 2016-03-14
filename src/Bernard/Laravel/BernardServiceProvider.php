<?php

namespace Bernard\Laravel;

use Bernard\Consumer;
use Bernard\Driver\PredisDriver;
use Bernard\Driver\SqsDriver;
use Bernard\EventListener\ErrorLogSubscriber;
use Bernard\EventListener\LoggerSubscriber;
use Bernard\Laravel\Driver\EloquentDriver;
use Bernard\Producer;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Router\SimpleRouter;
use Bernard\Serializer as BernardSerializer;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\EventDispatcher\EventDispatcher;

class BernardServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('bernard/laravel');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerDrivers();
        $this->registerSerializers();
        $this->registerHelpers();
        $this->registerCommands();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array();
    }

    /**
     * Overload package name to allow
     */
    protected function getPackageNamespace($package, $namespace)
    {
        return 'bernard';
    }


    /**
     * Register currently available Bernard drivers + custom for extension
     */
    protected function registerDrivers()
    {
        // SQS
        $this->app['bernard.driver.sqs'] = $this->app->share(function ($app) {
            $connection = $app['config']['bernard.connection'] ?: 'sqs';
            $queueUrls = $app['config']['bernard.queue_urls'] ?: [];
            $prefetch = $app['config']['bernard.prefetch'];

            $connectionInstance = is_object($connection) ? $connection : $app[$connection];
            return new SqsDriver($connectionInstance, $queueUrls, $prefetch);
        });

        // Predis
        $this->app['bernard.driver.predis'] = $this->app->share(function ($app) {
            $connection = $app['config']['bernard.connection'] ?: 'predis';
            $prefetch = $app['config']['bernard.prefetch'];

            $connectionInstance = is_object($connection) ? $connection : $app[$connection];
            return new PredisDriver($connectionInstance, $prefetch);
        });

        // Eloquent
        $this->app['bernard.driver.eloquent'] = $this->app->share(function ($app) {
            return new EloquentDriver();
        });

        // Custom
        $this->app['bernard.driver.custom'] = $this->app->share(function ($app) {

            // setup driver class name
            $className = studly_case($app['config']['bernard.driver']);
            if (false === strpos($className, '\\')) {
                $className = '\\Bernard\\Driver\\' . $className . 'Driver';
            }

            // determine key holding connection in IoC
            $connection = $app['config']['bernard.connection'];
            $connection = is_object($connection) ? $connection : $app[$connection];

            // setup driver
            if ($options = $app['config']['bernard.options']) {
                return new $className($connection, $options);
            } else {
                return new $className($connection);
            }
        });
    }

    protected function registerSerializers()
    {
        $this->app['bernard.serializer'] = $this->app->share(function ($app) {
            return new BernardSerializer;
        });
    }

    /**
     * Registers helper containers
     */
    protected function registerHelpers()
    {
        // actual driver
        $this->app['bernard.driver'] = $this->app->share(function ($app) {
            $driver = $app['config']['bernard.driver'];
            if (is_object($driver)) {
                return $driver;
            } else {
                $accessor = 'bernard.driver.' . snake_case($driver);
                if (!isset($app[$accessor])) {
                    $accessor = 'bernard.driver.custom';
                }

                return $app[$accessor];
            }
        });

        // queues
        $this->app['bernard.queues'] = $this->app->share(function ($app) {
            return new PersistentFactory($app['bernard.driver'], $app['bernard.serializer']);
        });

        // event dispatcher
        $this->app['bernard.event.dispatcher'] = $this->app->share(function ($app) {
            $dispatcher = new EventDispatcher;
            $dispatcher->addSubscriber(new ErrorLogSubscriber);
            $dispatcher->addSubscriber(new LoggerSubscriber($app['log']));
            return $dispatcher;
        });

        // the producer
        $this->app['bernard.producer'] = $this->app->share(function ($app) {
            return new Producer($app['bernard.queues'], $app['bernard.event.dispatcher']);
        });

        // the consumer
        $this->app['bernard.consumer'] = $this->app->share(function ($app) {

            $services = $app['config']['bernard.services'];

            $router = new SimpleRouter($services);

            return new Consumer($router, $this->app['bernard.event.dispatcher']);
        });
    }


    /**
     * Registers helper containers
     */
    protected function registerCommands()
    {
        $this->app['bernard.command.consume'] = $this->app->share(function () {
            return new Commands\BernardConsumeCommand;
        });
        $this->app['bernard.command.produce'] = $this->app->share(function () {
            return new Commands\BernardProduceCommand;
        });

        $this->commands(
            [
                'bernard.command.consume',
                'bernard.command.produce'
            ]
        );
    }
}
