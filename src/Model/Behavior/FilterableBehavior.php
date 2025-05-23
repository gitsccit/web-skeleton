<?php
declare(strict_types=1);

namespace Skeleton\Model\Behavior;

use Cake\Event\Event;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Routing\Router;

/**
 * Filterable behavior
 */
class FilterableBehavior extends Behavior
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $_defaultConfig = [
        'enabled' => false,
    ];

    protected $_request;

    protected array $_operationLookup = [
        'contains' => 'LIKE',
        'starts_with' => 'LIKE',
        'ends_with' => 'LIKE',
        'exact' => '=',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'ne' => '!=',
    ];

    protected string $_defaultOperation = 'contains';

    public function beforeFind(Event $event, Query $query, \ArrayObject $options, $primary)
    {
        $filterFields = $this->getConfigOrFail('fields');
        $genericFields = $this->getConfig('genericFields', $filterFields);
        $this->_request = Router::getRequest();
        $queryParams = $this->_request->getQueryParams();
        $table = $event->getSubject();
        $controllerName = $this->_request->getParam('controller');
        $tableName = $table->getAlias();
        $searchString = $queryParams['q'] ?? null;
        $genericSearch = !empty($searchString);
        unset($queryParams['q']);

        if ($genericSearch) {
            $filterFields = $genericFields;
        }

        if ($controllerName === $tableName) {
            $this->setConfig('enabled', true);
        }

        // execute only if filtering for this table is enabled, and adding conditions on the primary query only.
        if ($this->getConfig('enabled') && $primary) {
            // retrieve and lowercase all filterable entries for case-sensitive query param comparison.
            foreach ($filterFields as $key => $value) {
                unset($filterFields[$key]);

                if (is_numeric($key)) {
                    $key = $value;
                    $value = [$this->_defaultOperation];
                }

                // prefix current table fields with current able name, i.e. "name" -> "Users__name"
                if (!strpos($key, '__')) {
                    $key = "{$tableName}__$key";
                }

                $filterFields[$key] = $value;
            }

            if ($genericSearch) {
                foreach ($filterFields as $filterField => $filterOptions) {
                    if (empty($filterOptions)) {
                        $filterOptions = [$this->_defaultOperation];
                    }

                    $indexContain = array_search('contains', $filterOptions);
                    $indexExact = array_search('exact', $filterOptions);

                    if ($indexContain === false && $indexExact === false) {
                        continue;
                    } elseif ($indexContain === false) {
                        $operation = 'exact';
                    } elseif ($indexExact === false) {
                        $operation = 'contains';
                    } else {
                        $operation = $indexExact < $indexContain ? 'exact' : 'contains';
                    }

                    $queryParams["{$filterField}__$operation"] = $searchString;
                }
            }

            // add the `and` conditions. e.g. WHERE ... AND `name` LIKE "%James%" AND `age` <= 23.
            $conditions = [];
            foreach ($queryParams as $param => $value) {
                // get the fields in the query param, i.e. ['Users', 'age', 'lt'] for 'Users__age__lt'.
                $fields = explode('__', $param);

                // find the sql operation for the operation. i.e. 'lt' => '<', 'contains' => 'LIKE', etc.
                $operation = array_pop($fields);

                // not recognized operation
                if (!array_key_exists($operation, $this->_operationLookup)) {
                    continue;
                }

                // current table fields. e.g. turn ['name'] to ['Users', 'name'].
                if (count($fields) === 1) {
                    array_unshift($fields, $table->getAlias());
                }

                // check if the operation is permitted on this field.
                $param = implode('__', $fields);
                $allowedOperations = $filterFields[$param] ?? [];
                $sqlOperation = $this->_operationLookup[$operation];

                // skip unrecognized query params
                if (!isset($filterFields[$param])) {
                    continue;
                }

                if (!in_array($operation, $allowedOperations)) {
                    throw new BadRequestException("Operation '$operation' is not permitted on $param.");
                }

                // get the associations. e.g. ['Articles', 'Tags'] for 'Articles__Tags__name'.
                $association = implode('.', array_slice($fields, 0, -1));

                // construct sql field, e.g. 'Tags.name'.
                $sqlField = implode('.', array_slice($fields, -2));

                switch ($operation) {
                    case 'contains':
                        $value = "%$value%";
                        break;
                    case 'starts_with':
                        $value = "$value%";
                        break;
                    case 'ends_with':
                        $value = "%$value";
                        break;
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
                if ($table->hasAssociation($association)) {
                    $query->leftJoinWith($association);
                }

                $conditions["$sqlField $sqlOperation"] = $value;
            }

            $query->where($genericSearch ? ['OR' => $conditions] : $conditions);
        }
    }

    /**
     * Returns view variables `$filterNames`, `$genericFilterNames`, `$filterOperations`, `$selectedFilters`.
     *
     * @param Table|string|null $tableClass The class of the table that you want to filter
     */
    public function getFilterVariables()
    {
        $filterFields = $this->getConfigOrFail('fields');
        $genericFields = $this->getConfig('genericFields', []);
        $filterNames = $this->getConfig('names', []);
        $genericFilterNames = [];
        $filterOperations = [];
        $tableName = $this->_table->getAlias();

        foreach ($filterFields as $filterField => $operations) {
            if (is_numeric($filterField)) { // e.g. 0 => 'title'
                $filterField = $operations;
                $operations = null;
            }

            $addToGenericField = empty($genericFields) ? true : in_array($filterField, $genericFields);
            $filterName = $filterNames[$filterField] ?? null;

            // filter names
            if (empty($filterName)) {
                $fields = explode('__', $filterField);
                if (count($fields) === 1) { // e.g. 'first_name'
                    $filterName = $filterField; // 'first_name'
                    $filterField = "{$tableName}__$filterName"; // 'Users__first_name'
                } else {  // e.g. turn 'Users__first_name' to 'User First Name'
                    $field = array_pop($fields);
                    $associations = array_map('Cake\Utility\Inflector::singularize', $fields);
                    $fields = array_merge($associations, [$field]);
                    $filterName = implode(' ', $fields);
                }

                $filterName = humanize($filterName);
            }

            $filterNames[$filterField] = $filterName;

            if ($addToGenericField) {
                $genericFilterNames[$filterField] = $filterName;
            }

            // filter operations
            $filterOperations[$filterField] = $operations;
        }

        $defaultSelectedFilters = array_map(function ($key) use ($filterOperations) {
            $operation = $filterOperations[$key][0] ?? $this->_defaultOperation;
            return "{$key}__$operation";
        }, array_keys($filterNames));
        $selectedFilters = array_combine($defaultSelectedFilters, array_fill(0, count(array_keys($filterNames)), null));
        foreach ($this->_request->getQueryParams() as $key => $filterName) {
            $parts = explode('__', $key);
            $last = array_pop($parts);

            if (!array_key_exists($last, $this->_operationLookup)) {
                $parts[] = $last;
            }
            $filterField = implode('__', $parts);

            if (array_key_exists($filterField, $filterNames)) {
                $selectedFilters[$key] = $filterName;
            }
        }

        return compact('filterNames', 'genericFilterNames', 'filterOperations', 'selectedFilters');
    }

    /**
     * Allows filtering on actions other than `index`.
     */
    public function enableFiltering()
    {
        $this->setConfig('enabled', true);
    }

    /**
     * Allows filtering only on `index` action.
     */
    public function disableFiltering()
    {
        $this->setConfig('enabled', false);
    }

    public function setFilterFields($fields = [], $merge = false)
    {
        $this->setConfig('fields', $fields, $merge);
    }

    public function setFilterNames($names = [], $merge = false)
    {
        $this->setConfig('names', $names, $merge);
    }
}
