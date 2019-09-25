<?php

namespace Skeleton\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Event\Event;
use Cake\ORM\Association;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * Crud component
 */
class CrudComponent extends Component
{

    /**
     * Reference to the current controller.
     *
     * @var \Cake\Controller\Controller
     */
    protected $_controller;

    /**
     * The table instance associated with the current controller.
     *
     * @var \Cake\ORM\Table
     */
    protected $_table;

    /**
     * Reference to the current request.
     *
     * @var \Cake\Http\ServerRequest
     */
    protected $_request;

    /**
     * The class name of the entity associated with the current table.
     *
     * @var string
     */
    protected $_entityClass;

    /**
     * The current controller action.
     *
     * @var string
     */
    protected $_action;

    /**
     * Whether the response should be serialized, i.e. JSON or XML.
     * @var bool
     */
    protected $_serialized;

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'fallbackTemplatePath' => 'Common'
    ];

    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->_controller = $registry->getController();
        $config = $this->_controller->crud ?? $config;

        parent::__construct($registry, $config);
    }

    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->_table = $this->_controller->{$this->_controller->getName()};
        $this->_request = $this->_controller->getRequest();
        $this->_action = $this->_request->getParam('action');

        $acceptsContentTypes = $this->_request->accepts();
        $this->_serialized = !empty(array_intersect(['application/json', 'application/xml'], $acceptsContentTypes))
            && !in_array('text/html', $acceptsContentTypes);
        $this->_entityClass = $this->_table->getEntityClass();
    }

    public function beforeRender(Event $event)
    {
        $data = $this->_controller->viewVars;

        if ($this->_serialized) {
            return $this->serialize($data);
        }

        $template = $this->_controller->viewBuilder()->getTemplate();

        // set template
        $templateName = Inflector::underscore($this->_action) . '.ctp';
        $fallbackTemplatePath = $this->getConfig('fallbackTemplatePath');
        if ($template) {
            $components = explode(DS, $template);
            if (count($components) > 1) {
                $template = array_pop($components);
                $this->_controller->viewBuilder()->setTemplatePath(implode(DS, $components));
            }
            $this->_controller->viewBuilder()->setTemplate($template);
        } elseif (!file_exists(App::path('Template/' . $this->_viewPath())[0] . $templateName)
            && file_exists(App::path("Template/$fallbackTemplatePath")[0] . $templateName)) {
            $this->_controller->viewBuilder()->setTemplatePath($fallbackTemplatePath);
        }

        // set view variables
        $className = $this->_controller->getName();
        $displayField = $this->_table->getDisplayField();
        $title = Inflector::humanize($this->_table->getAlias());
        $name = $this->_action === 'index' ? 'entities' : 'entity';
        $entityName = $this->_action === 'index' ? $className : Inflector::classify($className);
        $entity = $data[lcfirst($entityName)];
        $this->_controller->set([$name => $entity]);

        // set $accessibleFields and $viewVars if action requires user input
        if (in_array($this->_action, ['add', 'edit'])) {
            $belongsTo = array_map(function (Association $association) {
                return $association->getName();
            }, $this->_table->associations()->getByType('BelongsTo'));

            $entityFields = array_merge($this->_table->getSchema()->columns(),
                array_map([Inflector::class, 'underscore'], $belongsTo));
            $accessibleFields = array_filter($entityFields, function ($field) {
                return (new $this->_entityClass())->isAccessible($field);
            });
            $this->_controller->set(compact('accessibleFields'));
        }

        // set $filterableFields if $filterable is set for the entity
        $this->_setFilterOptions();

        // set the extra view variables
        $this->_controller->set(compact('className', 'displayField', 'title'));
    }

    /**
     * Handles pagination of records in Table objects as well as their associations.
     *
     * Will load the referenced Table object, and have the PaginatorComponent
     * paginate the query using the request date and settings defined in `$this->paginate`.
     *
     * This method will also make the PaginatorHelper available in the view.
     *
     * @param string|\Cake\ORM\Query|null $object Table to paginate
     * (e.g: Table instance, 'TableName' or a Query object)
     * @param array $settings The settings/configuration used for pagination.
     * @return array Query results
     * @throws \RuntimeException When no compatible table object can be found.
     * @link https://book.cakephp.org/3.0/en/controllers.html#paginating-a-model
     */
    public function paginateAssociations($object = null, array $settings = [])
    {
        $associationTypes = ['BelongsToMany', 'HasMany'];
        if ($object instanceof Query) {
            $table = $object->getRepository();
            $contain = $object->getContain();

            // get a list of BelongsTo associations
            $belongsTo = array_map(function (Association $association) {
                return $association->getName();
            }, $table->associations()->getByType('BelongsTo'));

            // reset 'contain' of the query to only the BelongsTo associations, construct base query
            $entity = $object->contain(array_intersect(array_keys($contain), $belongsTo), true)->first();
            $queries = [];

            // get all associated tables of the types in $associationTypes
            $associations = $table->associations()->getByType($associationTypes);

            // construct queries for the associated tables
            foreach ($associations as $association) {
                $associatedTable = $association->getTarget();

                // if association is in original 'contain'
                if (isset($contain[$association->getName()])) {
                    $nestedContain = $contain[$association->getName()] ?? [];
                    $query = $associatedTable->find()->contain($nestedContain);

                    if ($association instanceof Association\HasMany) {
                        $query->where(["{$associatedTable->getAlias()}.{$association->getForeignKey()}" => $entity->id]);
                    } elseif ($association instanceof Association\BelongsToMany) {
                        foreach ($associatedTable->associations()->getByType('BelongsToMany') as $assoc) {
                            if ($assoc->getTargetForeignKey() === $association->getForeignKey()) {
                                $aliasOfCurrentTableOnAssociatedTable = $assoc->getName();
                                break;
                            }
                        }
                        $query->innerJoinWith($aliasOfCurrentTableOnAssociatedTable,
                            function (Query $q) use ($aliasOfCurrentTableOnAssociatedTable, $entity) {
                                return $q->where(["$aliasOfCurrentTableOnAssociatedTable.id" => $entity->id]);
                            });
                    }
                    $queries[] = $query;
                }
            }
        } elseif ($object instanceof Table) {
            $table = $object;
        } elseif (is_string($object)) {
            $table = TableRegistry::getTableLocator()->get($object);
        } else {
            $table = $this->_table;
        }

        $this->_controller->loadComponent('Paginator');
        if (empty($table)) {
            throw new \RuntimeException('Unable to locate an object compatible with paginate.');
        }

        if (!$object instanceof Query) {
            $tables = array_merge([$table], array_map(function ($association) {
                return $association->getTarget();
            }, $table->associations()->getByType($associationTypes)));
            $queries = array_map(function (Table $table) {
                return $table->find();
            }, $tables);
        }

        $settings += $this->_controller->paginate;

        $resultSets[lcfirst(Inflector::classify($table->getAlias()))] = $entity;
        foreach ($queries as $query) {
            $field = Inflector::variable($query->getRepository()->getRegistryAlias());
            $fieldSettings = array_merge($settings, ['scope' => Inflector::dasherize($field)]);
            $resultSets['associations'][$field] = $this->_controller->Paginator->paginate($query, $fieldSettings);
        }

        return $resultSets;
    }

    /**
     * Serializes the response body, i.e. json/xml
     * @param array|string|EntityInterface|ResultSetInterface $data
     * @param int $status
     */
    public function serialize($data = [], $status = 200)
    {
        if (is_int($data) && 100 <= $data && $data < 600) {
            $status = $data;
            $data = [];
        } elseif (is_string($data)) {
            $data = ['message' => $data];
        } elseif ($data instanceof EntityInterface || $data instanceof ResultSetInterface) {
            $data = $data->toArray();
        } elseif (!$data) {
            $data = [];
        }

        $this->_controller->setResponse($this->_controller->getResponse()->withStatus($status));
        $this->_controller->set(array_merge($data, ['_serialize' => array_keys($data)]));
    }

    /**
     * Get the viewPath based on controller name and request prefix.
     *
     * @return string
     */
    protected function _viewPath()
    {
        $viewPath = $this->_controller->getName();
        if ($this->_request->getParam('prefix')) {
            $prefixes = array_map(
                'Cake\Utility\Inflector::camelize',
                explode('/', $this->_request->getParam('prefix'))
            );
            $viewPath = implode(DIRECTORY_SEPARATOR, $prefixes) . DIRECTORY_SEPARATOR . $viewPath;
        }

        return $viewPath;
    }

    protected function _setFilterOptions()
    {
        if (($entityClass = $this->_entityClass) && property_exists($entityClass, 'filterable')) {
            $filterOptions = [];
            foreach ($entityClass::$filterable as $key => $field) {
                // current table field
                if (is_numeric($key)) {
                    $filterOptions["{$this->_table->getAlias()}.$field"] = Inflector::humanize($field);
                    continue;
                }

                // associated table fields
                $assoc = ucfirst($key);
                if (!is_array($field)) {
                    $field = [$field];
                }

                foreach ($field as $subField) {
                    $value = Inflector::pluralize($assoc) . ".$subField";
                    $filterOptions[$assoc][$value] = Inflector::humanize($subField);
                }
            }
            $this->_controller->set(compact('filterOptions'));
        }
    }
}

