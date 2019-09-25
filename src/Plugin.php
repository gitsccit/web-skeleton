<?php

namespace Skeleton;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\SecurityHeadersMiddleware;
use Skeleton\Listener\DataSource;
use Skeleton\Middleware\RequestSanitationMiddleware;

/**
 * Plugin for Skeleton
 */
class Plugin extends BasePlugin
{
    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middleware The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware($middleware)
    {
        $securityHeaders = new SecurityHeadersMiddleware();
        $securityHeaders
            ->setCrossDomainPolicy()
            ->setReferrerPolicy()
            ->setXFrameOptions()
            ->setXssProtection()
            ->noOpen()
            ->noSniff();

        $middleware->prepend($securityHeaders);
        $middleware->add(new BodyParserMiddleware(['xml' => true]));
        $middleware->add(new RequestSanitationMiddleware());

        return $middleware;
    }

    public function bootstrap(PluginApplicationInterface $app)
    {
        $app->addPlugin('Migrations');
        EventManager::instance()->on(new DataSource());
        parent::bootstrap($app);
    }
}
