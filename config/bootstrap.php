<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

/*
 * Add custom global functions.
 */
require 'functions.php';
require 'ApiHandler.php';

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
$plugins = array_merge(array_diff_improved(Plugin::loaded(), ['DebugKit', 'Migrations']), ['test']);
foreach ($plugins as $plugin) {
    $plugin = Inflector::underscore($plugin);
    foreach (['default', 'replica'] as $dataSource) {
        $config = ConnectionManager::getConfig($dataSource);
        $config['database'] = $plugin;
        $config['name'] = $dataSource === 'default' ? $plugin : "${plugin}_replica";
        if (is_null(ConnectionManager::getConfig($config['name']))) {
            ConnectionManager::setConfig($config['name'], $config);
        }
    }
}

if ($cache = Configure::consume('Cache')) {
    Cache::setConfig($cache);
}
