<?php

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\Folder;
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

    // add db config for plugins
    $plugins = (new Folder())->subdirectories(ROOT . DS . 'plugins', false);
    foreach ($plugins as $plugin) {
        $plugin = Inflector::underscore($plugin);
        foreach (['default', 'default_master'] as $dataSource) {
            $config = ConnectionManager::getConfig($dataSource);
            $config['database'] .= "_$plugin";
            $config['name'] = str_replace('default', $plugin, $config['name']);
            ConnectionManager::setConfig($config['name'], $config);
        }
    }
} catch (\Exception $e) {
    exit($e->getMessage() . "\n");
}
