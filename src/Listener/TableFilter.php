<?php
declare(strict_types=1);

namespace Skeleton\Listener;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Query;

class TableFilter implements EventListenerInterface
{
    /**
     * @var \Cake\Controller\Controller
     */
    protected $_controller;

    public function __construct($controller)
    {
        $this->_controller = $controller;
    }

    public function implementedEvents(): array
    {
        return [
            'Model.beforeFind' => 'beforeFind',
        ];
    }

    public function beforeFind(Event $event, Query $query, \ArrayObject $options, $primary)
    {
        $queryParams = $this->_controller->getRequest()->getQueryParams();
        $controllerName = $this->_controller->getName();
        $tableName = $event->getSubject()->getAlias();
        $filter = $queryParams['filter'] ?? null;
        $key = $queryParams['key'] ?? null;

        if ($controllerName === $tableName && $primary && $filter && $key) {
            $query->andWhere([["$filter LIKE" => "%$key%"]]);
        }
    }
}
