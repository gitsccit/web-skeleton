<?php
declare(strict_types=1);

namespace Skeleton\Controller\Component;

use Cake\Collection\Collection;
use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Event\Event;
use Cake\Http\Exception\NotFoundException;
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
    protected array $_defaultConfig = [
        'fallbackTemplatePath' => 'Common',
    ];

    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->_controller = $registry->getController();
        $config = isset($this->_controller->crud) ? $this->_controller->crud : $config;

        parent::__construct($registry, $config);
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $request = $this->_controller->getRequest();
        $this->_action = $request->getParam('action');
        $this->_serialized = $request->is(['json']);

        $plugin = $this->_controller->getPlugin();
        $modelClass = ($plugin ? "$plugin." : '') . $this->_controller->getName();
        $this->_table = App::className($modelClass, 'Model\Table', 'Table') ?
            TableRegistry::getTableLocator()->get($modelClass) : null;
    }

    public function beforeRender(Event $event)
    {
        $data = $this->_controller->viewBuilder()->getVars();

        if ($this->_controller->viewBuilder()->getOption('serialize')) {
            $this->_controller->viewBuilder()->setClassName('Json');
        } elseif ($this->_serialized) {
            $this->serialize($data);
        } elseif ($this->_table) {
            $template = $this->_controller->viewBuilder()->getTemplate();

            // set template
            $templateName = Inflector::underscore($this->_action) . '.php';
            $fallbackTemplatePath = $this->getConfig('fallbackTemplatePath');
            [$plugin, $templateFolder] = pluginSplit($fallbackTemplatePath);
            $basePath = is_null($plugin) ? App::path('templates')[0] : App::path('templates', $plugin)[0];
            if ($template) {
                $components = explode(DS, $template);
                if (count($components) > 1) {
                    $template = array_pop($components);
                    $this->_controller->viewBuilder()->setTemplatePath(implode(DS, $components));
                }
                $this->_controller->viewBuilder()->setTemplate($template);
            } elseif (
                !file_exists(App::path('templates')[0] . $this->_viewPath() . $templateName)
                && file_exists($basePath . $templateFolder . DS . $templateName)
            ) {
                $this->_controller->viewBuilder()->setTheme($plugin);
                $this->_controller->viewBuilder()->setTemplatePath($templateFolder);
            }

            // set view variables
            $className = $this->_controller->getName();
            $displayField = $this->_table->getDisplayField();
            $title = humanize($this->_table->getAlias());

            // set $entities / $entity
            $name = $this->_action === 'index' ? 'entities' : 'entity';
            $entityName = lcfirst($this->_action === 'index' ? $className : Inflector::classify($className));
            if ($entity = $data[$entityName] ?? null) {
                $this->_controller->set([$name => $entity]);
            }

            // set $accessibleFields and $viewVars if action requires user input
            if (in_array($this->_action, ['add', 'edit'])) {
                $belongsTo = array_map(function (Association $association) {
                    return $association->getName();
                }, $this->_table->associations()->getByType('BelongsTo'));

                $entityFields = array_merge(
                    $this->_table->getSchema()->columns(),
                    array_map([Inflector::class, 'underscore'], $belongsTo)
                );
                $accessibleFields = array_filter($entityFields, function ($field) {
                    $entityClass = $this->_table->getEntityClass();

                    return (new $entityClass())->isAccessible($field) &&
                        !in_array($field, ['id', 'created_at', 'modified_at', 'updated_at']);
                });
                $accessibleFields = array_combine($accessibleFields, array_fill(0, count($accessibleFields), []));
                $this->_controller->set(compact('accessibleFields'));
            }

            // set the extra view variables
            $this->_controller->set(compact('className', 'displayField', 'title'));
        }
    }

    /**
     * Serializes the response body, i.e. json/xml
     * @param array|string|\Cake\Datasource\EntityInterface|\Cake\Datasource\ResultSetInterface $data
     * @param int $status
     */
    public function serialize($data = [], $status = 200)
    {
        if (is_int($data) && 100 <= $data && $data < 600) {
            $status = $data;
            $data = [];
        } elseif (is_string($data)) {
            $data = ['messages' => [$data]];
        } elseif ($data instanceof EntityInterface || $data instanceof ResultSetInterface) {
            $data = $data->toArray();
        } elseif (!$data) {
            $data = [];
        }

        $this->_controller->setResponse($this->_controller->getResponse()->withStatus($status));
        $this->_controller->set($data);
        $this->_controller->viewBuilder()->setOption('serialize', array_keys($data));
    }

    /**
     * Get the viewPath based on controller name and request prefix.
     *
     * @return string
     */
    protected function _viewPath()
    {
        $viewPath = $this->_controller->getName();
        $request = $this->_controller->getRequest();

        if ($request->getParam('prefix')) {
            $prefixes = array_map(
                'Cake\Utility\Inflector::camelize',
                explode('/', $request->getParam('prefix'))
            );
            $viewPath = implode(DS, $prefixes) . DS . $viewPath;
        }

        return $viewPath . DS;
    }

    /**
     * Helper method to reproduce contain for associations. (For paginating associations)
     * By default Cake converts find(contain: ['Users' => ['limit' => 5]]) to ['Users' => ['limit' => [5 => []]]],
     * This method converts it back to ['Users' => ['limit' => 5]].
     *
     * @param array $contain
     * @return array|mixed
     */
    protected function _formatContain(array $contain)
    {
        $result = [];

        foreach ($contain as $field => $value) {
            if ($field === 'fields') {
                $result[$field] = $value;
                continue;
            }

            if (empty($value)) {
                $result[] = $field;
                continue;
            }

            $result[$field] = $this->_formatContain($value);
        }

        if (!is_assoc($result) && count($result) === 1) {
            $result = $result[0];
        }

        return $result;
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

            if (!$entity) {
                throw new NotFoundException();
            }

            $queries = [];

            // get all associated tables of the types in $associationTypes
            $associations = $table->associations()->getByType($associationTypes);
            $associations = (new Collection($associations))->indexBy(function ($association) {
                // index by the name of the association for easier access later
                return $association->getName();
            })->toArray();

            // construct queries for the associated tables
            foreach ($contain as $containField => $nestedContain) {
                // if association is in original 'contain'
                if (isset($associations[$containField])) {
                    $association = $associations[$containField];
                    $associatedTable = $association->getTarget();

                    $query = $associatedTable->find(contain: $nestedContain);

                    if ($association instanceof Association\HasMany) {
                        $query->where(["{$associatedTable->getAlias()}.{$association->getForeignKey()}" => $entity->id]);
                    } elseif ($association instanceof Association\BelongsToMany) {
                        foreach ($associatedTable->associations()->getByType('BelongsToMany') as $assoc) {
                            if ($assoc->getTargetForeignKey() === $association->getForeignKey()) {
                                $aliasOfCurrentTableOnAssociatedTable = $assoc->getName();
                                break;
                            }
                        }
                        $query->innerJoinWith(
                            $aliasOfCurrentTableOnAssociatedTable,
                            function (Query $q) use ($aliasOfCurrentTableOnAssociatedTable, $entity) {
                                return $q->where(["$aliasOfCurrentTableOnAssociatedTable.id" => $entity->id]);
                            }
                        );
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

        if (isset($this->_controller->paginate)) {
            $settings += $this->_controller->paginate;
        }

        $resultSets[lcfirst(Inflector::classify($table->getAlias()))] = $entity;
        $resultSets['associations'] = [];
        foreach ($queries as $query) {
            $table = $query->getRepository();
            $field = Inflector::variable($table->getAlias());
            $fieldSettings = array_merge($settings, ['scope' => Inflector::dasherize($field)]);
            $result = $this->_controller->paginate($query, $fieldSettings);
            $resultSets['associations'][$field] = $result;
        }

        return $resultSets;
    }
}
