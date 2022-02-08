<?php
/** @var \Cake\Routing\RouteBuilder $routes */

use Cake\Routing\RouteBuilder;

$routes->plugin('Skeleton', ['path' => '/'], function (RouteBuilder $builder) {
    $builder->connect('/files/download/*', ['controller' => 'Files', 'action' => 'download']);
    $builder->connect('/files/**', ['controller' => 'Files', 'action' => 'open']);
});