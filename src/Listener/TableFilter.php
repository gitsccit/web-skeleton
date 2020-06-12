<?php
declare(strict_types=1);

namespace Skeleton\Listener;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\Association\BelongsToMany;
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

        // skip functions that are not index
        if ($this->_controller->getRequest()->getParam('action') !== 'index') {
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

                // current table fields. e.g. turn ['name'] to ['users', 'name'].
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

                // get the associations. e.g. ['articles', 'tags'] for 'Articles__Tags__name'.
                $associations = array_slice($fields, 0, -1);

                // construct sql field, e.g. 'tags.name'.
                $sqlField = implode('.', array_slice($fields, -2));

                if ($sqlOperation === 'LIKE') {
                    $value = "%$value%";
                }

                // change '= NULL' to 'IS NULL' and '!= NULL' to 'IS NOT NULL'.
                if ($value === 'NULL') {
                    if ($sqlOperation === '=') {
                        $sqlOperation = 'IS';
                    } elseif ($sqlOperation === '!=') {
                        $sqlOperation = 'IS NOT';
                    }
                    $value = null;
                }

                // construct query
                $this->updateQuery($query, $associations, ["$sqlField $sqlOperation" => $value]);
            }
        }
    }

    protected function updateQuery(Query $query, array $associations, array $condition)
    {
        $table = $query->getRepository();

        $association = array_shift($associations);

        // apply the condition if there's no more associations to join with.
        if (!$association) {
            return $query->where($condition);
        }

        // add many to many joins
        if ($table->hasAssociation($association) && $table->getAssociation($association) instanceof BelongsToMany) {
            return $query->innerJoinWith($association, function (Query $q) use ($associations, $condition) {
                return $this->updateQuery($q, $associations, $condition);
            });
        }

        // simple joins
        if (strcasecmp($table->getRegistryAlias(), $association) !== 0) {
            return $query->contain([
                $association => function (Query $q) use ($associations, $condition) {
                    return $this->updateQuery($q, $associations, $condition);
                },
            ]);
        }

        return $this->updateQuery($query, $associations, $condition);
    }
}
