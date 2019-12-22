<?php
declare(strict_types=1);

namespace Skeleton\Listener;

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Query;

class DataSource implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.beforeDelete' => 'beforeDelete',
        ];
    }

    public function beforeFind(Event $event, Query $query, \ArrayObject $options, $primary)
    {
        $table = $event->getSubject();
        $configName = $table->getConnection()->configName();

        if (!endsWith($configName, 'replica') && !$table->getConnection()->inTransaction()) {
            $config = $configName === 'default' ? 'replica' : "${configName}_replica";
            $table->setConnection(ConnectionManager::get($config));
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

        if (endsWith($configName, 'replica')) {
            $event->stopPropagation();
            $table->getConnection()->rollback();
            $config = $configName === 'replica' ? 'default' : str_replace('_replica', '', $configName);
            ConnectionManager::alias($config, $configName);
            $table->setConnection(ConnectionManager::get($configName));
            $event->setResult($table->$action($event->getData('entity'), $event->getData('options')->getArrayCopy()));
            ConnectionManager::dropAlias($configName);
        }
    }
}
