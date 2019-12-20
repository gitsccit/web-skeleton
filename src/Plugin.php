<?php
declare(strict_types=1);

namespace Skeleton;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\SecurityHeadersMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Skeleton\Listener\DataSource;
use Skeleton\Middleware\RequestSanitationMiddleware;

/**
 * Plugin for Skeleton
 */
class Plugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * The host application is provided as an argument. This allows you to load
     * additional plugin dependencies, or attach events.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        $app->addPlugin('Migrations');
        EventManager::instance()->on(new DataSource());
        parent::bootstrap($app);
    }

    /**
     * Add routes for the plugin.
     *
     * If your plugin has many routes and you would like to isolate them into a separate file,
     * you can create `$plugin/config/routes.php` and delete this method.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('Skeleton', ['path' => '/'], function (RouteBuilder $builder) {
            $builder->fallbacks();
        });
    }

    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middleware The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $securityHeaders = new SecurityHeadersMiddleware();
        $securityHeaders
            ->setCrossDomainPolicy()
            ->setReferrerPolicy()
            ->setXFrameOptions()
            ->setXssProtection()
            ->noOpen()
            ->noSniff();

        $middlewareQueue->prepend($securityHeaders);
        $middlewareQueue->add(new BodyParserMiddleware(['xml' => true]));
        $middlewareQueue->add(new RequestSanitationMiddleware());

        return $middlewareQueue;
    }
}
