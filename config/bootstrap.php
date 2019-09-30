<?php

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

/*
 * Add custom global functions.
 */
require 'functions.php';

/*
 * Read configuration file and inject configuration into various
 * CakePHP classes.
 *
 * By default there is only one configuration file. It is often a good
 * idea to create multiple configuration files, and separate the configuration
 * that changes from configuration that does not. This makes deployment simpler.
 */
try {
    Configure::load('Skeleton.app', 'default', true);
} catch (\Exception $e) {
    exit($e->getMessage() . "\n");
}

// add db config for plugins
$plugins = array_diff_improved(Plugin::loaded(), ['DebugKit', 'Migrations']);
foreach ($plugins as $plugin) {
    $plugin = Inflector::underscore($plugin);
    foreach (['default', 'default_master'] as $dataSource) {
        $config = ConnectionManager::getConfig($dataSource);
        $config['database'] = $plugin;
        $config['name'] = str_replace('default', $plugin, $config['name']);
        ConnectionManager::setConfig($config['name'], $config);
    }
}

if ($cache = Configure::consume('Cache')) {
    Cache::setConfig($cache);
}
