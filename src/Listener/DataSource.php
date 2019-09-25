<?php

namespace Skeleton\Listener;

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Query;

class DataSource implements EventListenerInterface
{

    public function implementedEvents()
    {
        return [
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.beforeDelete' => 'beforeDelete'
        ];
    }

    public function beforeFind(Event $event, Query $query, \ArrayObject $options, $primary)
    {
        $table = $event->getSubject();
        $configName = $table->getConnection()->configName();
        if (endsWith($configName, '_master')) {
            ConnectionManager::alias(str_replace('_master', '', $configName), 'default');
        }
    }

    public function beforeSave(Event $event, EntityInterface $entity, \ArrayObject $options)
    {
        $this->rerouteAction($event, 'save');
    }

    public function beforeDelete(Event $event, EntityInterface $entity, \ArrayObject $options)
    {
        $this->rerouteAction($event, 'delete');
    }

    protected function rerouteAction(Event $event, $action)
    {
        $table = $event->getSubject();
        $configName = $table->getConnection()->configName();
        if (!endsWith($configName, '_master')) {
            $event->stopPropagation();
            $table->getConnection()->rollback();
            ConnectionManager::alias("${configName}_master", 'default');
            $table->setConnection(ConnectionManager::get('default'));
            $event->setResult($table->$action($event->getData('entity'), $event->getData('options')->getArrayCopy()));
        }
    }
}
