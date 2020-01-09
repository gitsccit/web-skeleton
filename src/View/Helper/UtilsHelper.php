<?php
declare(strict_types=1);

namespace Skeleton\View\Helper;

use Cake\Collection\CollectionInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\View\Helper;

/**
 * Utils helper
 *
 * @property \Cake\View\Helper\FormHelper $Form
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class UtilsHelper extends Helper
{
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

    public $helpers = ['Form', 'Html'];

    /**
     * @param mixed $value
     * @return string Parsed string.
     */
    public function display($value)
    {
        if (is_numeric($value) && !is_string($value)) {
            $value = (string)$value;
        } elseif (is_bool($value)) {
            $value = $value ? __('Yes') : __('No');
        } elseif ($value instanceof EntityInterface) {
            $table = TableRegistry::getTableLocator()->get($value->getSource());
            [$plugin, ] = pluginSplit($table->getRegistryAlias());
            $value = $this->Html->link(
                $value->{$table->getDisplayField()},
                ['controller' => $table->getTable(), 'action' => 'view', $value->id, 'plugin' => $plugin]
            );
        } elseif ($value instanceof \DateTimeInterface) {
            $timezone = $this->_View->getRequest()->getSession()->read('Auth.User.time_zone.name');
            $value = $this->_View->Time->format($value, null, null, $timezone);
        } elseif (empty($value)) {
            $value = "—";
        } elseif (is_string($value) && $value === strip_tags($value)) {
            $value = h($value);
        }

        return $value;
    }

    /**
     * Parses a list of entities into displayable table cells, with `Actions` as the last column.
     *
     * @param \Cake\ORM\Table|\Cake\Collection\CollectionInterface|array|string $entities A list of entities to be parsed, a table object, or the alias of the table.
     * @param array $options - `actions`: A list of actions that the table will include, supports `view`, `edit`, `delete`, defaults to `view`, `edit`.
     * @return string
     */
    public function createTable($entities, array $options = [])
    {
        if ($entities instanceof Table || is_string($entities)) {
            $table = is_string($entities) ? TableRegistry::getTableLocator()->get($entities) : $entities;
            $entityClass = $table->getEntityClass();
            $entity = new $entityClass();
            $visibleFields = array_diff_improved(
                array_merge($table->getSchema()->columns(), $entity->getVirtual()),
                $entity->getHidden()
            );
            $entities = [];
        } elseif (($entities instanceof CollectionInterface && $entities->count() > 0) || (is_array($entities) && !empty($entities))) {
            if ($entities instanceof CollectionInterface) {
                $entities = $entities->toArray();
            }
            $entity = $entities[0];
            $table = TableRegistry::getTableLocator()->get($entity->getSource());
            $visibleFields = array_filter(array_keys($entity->toArray()), function ($field) use ($entity) {
                // remove array fields, they can't be displayed in a table
                return !is_array($entity->$field);
            });
        } else {
            throw new \RuntimeException('Entities have an invalid type: ' . get_class($entities));
        }

        $displayField = $table->getDisplayField();
        $controller = $table->getTable();

        // set priority
        $priority = $entity::$priority ?? [];
        if ($priority) {
            $defaultPriorities = array_combine($visibleFields, array_fill(0, count($visibleFields), 0));
            $priority = array_flip(array_combine(range(1, count($priority)), $priority));
            $priority = array_merge($defaultPriorities, array_intersect_key($priority, $defaultPriorities));
            asort($priority);
            $visibleFields = array_keys($priority);
        }

        // construct the header of the table
        $headers = array_map(function ($field) {
            return $this->_View->Paginator->sort($field);
        }, $visibleFields);

        // add Action column if `actions` option is set
        $allowedActions = $options['actions'] ?? ['view', 'edit'];
        if ($allowedActions) {
            $headers = array_merge($headers, ['Actions']);
        }

        $this->_View->Paginator->defaultModel($table->getAlias());
        $thead = $this->Html->tableHeaders($headers);

        // construct the body of the table
        $tbody = array_map(function (EntityInterface $entity) use ($allowedActions, $controller, $displayField, $visibleFields) {
            $view = $this->Html->link(__('View'), ['controller' => $controller, 'action' => 'view', $entity->id]);
            $edit = $this->Html->link(
                '<i class="icon-edit"></i>' . __('Edit'),
                ['controller' => $controller, 'action' => 'edit', $entity->id],
                ['escape' => false]
            );
            $delete = $this->Form->postLink(
                '<i class="icon-trash-empty"></i>' . __('Delete'),
                ['controller' => $controller, 'action' => 'delete', $entity->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $entity->$displayField), 'escape' => false]
            );

            // remove `view` button, use `displayField` as a hyperlink instead.
            if ($displayField !== 'id') {
                $key = array_search('view', $allowedActions);
                unset($allowedActions[$key]);
            }

            // convert action name to links.
            foreach ($allowedActions as $action) {
                $actions[] = $$action;
            }

            $cells = array_map(function ($field) use ($controller, $displayField, $entity) {
                return $field !== $displayField ? $this->display($entity->$field) :
                    $this->Html->link(
                        __($this->display($entity->$displayField)),
                        ['controller' => $controller, 'action' => 'view', $entity->id]
                    );
            }, $visibleFields);

            // construct actions string
            if ($actions = implode(' | ', $actions ?? [])) {
                $cells[] = $actions;
            }

            return $this->Html->tableCells($cells);
        }, $entities);

        return $this->_View->element('Skeleton.table', compact('thead', 'tbody', 'priority'));
    }
}
