<?php
declare(strict_types=1);

namespace Skeleton\Listener;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\Query;

class TableFilter implements EventListenerInterface
{
    /**
     * @var \Cake\Controller\Controller
     */
    protected $_controller;

    protected $_operationLookup = [
        'contains' => 'LIKE',
        'exact' => '=',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'ne' => '!=',
    ];

    protected $_defaultOperation = 'contains';

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
        $table = $event->getSubject();
        $tableName = $table->getAlias();
        $entityClass = $table->getEntityClass();

        // filter for current table is not enabled
        if (!isset($entityClass::$filterable)) {
            return $event;
        }

        // TODO: Delete this code block when query param is implememnted in UI.
        if (($filter = $queryParams['filter'] ?? null) && ($key = $queryParams['key'] ?? null)) {
            $queryParams[$filter] = $key;
            if ($operation = $queryParams['filter'] ?? null) {
                $filter = "{$filter}__$operation";
            }
            unset($queryParams['filter'], $queryParams['key']);
        }

        // prevent adding conditions on subqueries.
        if ($controllerName === $tableName && $primary) {

            // retrieve and lowercase all filterable entries for case-insensitive query param comparison.
            // Permitted operations are set in `$filterable` in the model's entity class.
            $filterable = [];
            foreach ($entityClass::$filterable as $key => $value) {
                if (is_numeric($key)) {
                    $key = $value;
                    $value = [$this->_defaultOperation];
                }

                // prefix current table fields with current able name, i.e. "name" -> "Users__name"
                if (!strpos($key, '__')) {
                    $key = "{$tableName}__$key";
                }

                // lowercase the keys
                $filterable[strtolower($key)] = $value;
            }

            // add the `and` conditions. e.g. WHERE ... AND `name` LIKE "%James%" AND `age` <= 23.
            foreach ($queryParams as $param => $value) {
                $param = strtolower($param);

                // get the fields in the query param, i.e. ['users', 'age', 'lt'] for 'users__age__lt'.
                $fields = explode('__', $param);

                // find the sql operation for the operation. i.e. 'lt' => '<', 'contains' => 'LIKE', etc.
                $operation = array_pop($fields);
                $sqlOperation = $this->_operationLookup[$operation] ?? null;

                // not recognized operation, treat as nested relationship. e.g. 'name' in 'users__name'.
                if (!$sqlOperation) {
                    $fields[] = $operation;
                    $operation = $this->_defaultOperation;
                    $sqlOperation = $this->_operationLookup[$this->_defaultOperation];
                }

                // current table fields. e.g. turn ['name'] to ['users', 'name']
                if (count($fields) === 1) {
                    array_unshift($fields, strtolower($table->getAlias()));
                }

                // check if the operation is permitted on this field.
                $param = implode('__', $fields);
                $allowedOperations = $filterable[$param] ?? [];

                // skip unrecognized query params
                if (!isset($filterable[$param])) {
                    continue;
                }

                if (!in_array($operation, $allowedOperations)) {
                    throw new BadRequestException("Operation '$operation' is not permitted on $param.");
                }

                // construct sql field, e.g. 'users.name'
                $sqlField = implode('.', $fields);

                if ($sqlOperation === 'LIKE') {
                    $value = "%$value%";
                }

                $query->where(["$sqlField $sqlOperation" => $value]);
            }
        }
    }
}