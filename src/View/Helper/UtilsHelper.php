<?php

namespace Skeleton\View\Helper;

use Cake\Collection\CollectionInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\View\Helper;
use Cake\View\Helper\FormHelper;
use Cake\View\Helper\HtmlHelper;

/**
 * Utils helper
 *
 * @property FormHelper $Form
 * @property HtmlHelper $Html
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
            $value = $this->_View->Number->format($value);
        } elseif (is_bool($value)) {
            $value = $value ? __('Yes') : __('No');
        } elseif ($value instanceof EntityInterface) {
            $table = TableRegistry::getTableLocator()->get($value->getSource());
            $value = $this->Html->link($value->{$table->getDisplayField()},
                ['controller' => $table->getTable(), 'action' => 'view', $value->id]);
        } elseif ($value instanceof \DateTimeInterface) {
            $timezone = $this->_View->getRequest()->getSession()->read('Auth.User.time_zone');
            $value = $this->_View->Time->format($value, null, null, $timezone);
        } elseif (empty($value)) {
            $value = "—";
        } else {
            $value = h($value);
        }

        return $value;
    }

    /**
     * Parses a list of entities into displayable table cells, with `Actions` as the last column.
     *
     * @param Table|CollectionInterface|array|string $entities A list of entities to be parsed, or a table object.
     * @return string
     */
    public function createTable($entities)
    {
        if ($entities instanceof Table || is_string($entities)) {
            $table = is_string($entities) ? TableRegistry::getTableLocator()->get($entities) : $entities;
            $entityClass = $table->getEntityClass();
            $entity = new $entityClass();
            $visibleFields = array_diff_improved(array_merge($table->getSchema()->columns(), $entity->getVirtual()),
                $entity->getHidden());
            $entities = [];
        } elseif (($entities instanceof CollectionInterface && $entities->count() > 0) || (is_array($entities) && !empty($entities))) {
            if ($entities instanceof CollectionInterface) {
                $entities = $entities->toArray();
            }
            $entity = $entities[0];
            $table = TableRegistry::getTableLocator()->get($entity->getSource());
            $visibleFields = array_keys($entity->toArray());
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
        $this->_View->Paginator->defaultModel($table->getRegistryAlias());
        $thead = $this->Html->tableHeaders(
            array_merge(array_map(function ($field) {
                return $this->_View->Paginator->sort($field);
            }, $visibleFields), ['Actions'])
        );

        // construct the body of the table
        $tbody = array_map(function (EntityInterface $entity) use ($controller, $displayField, $visibleFields) {
            $view = $this->Html->link(__('View'), ['controller' => $controller, 'action' => 'view', $entity->id]);
            $edit = $this->Html->link(__('Edit'), ['controller' => $controller, 'action' => 'edit', $entity->id]);
            $delete = $this->Form->postLink(__('Delete'),
                ['controller' => $controller, 'action' => 'delete', $entity->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $entity->$displayField)]);
            $actions = $displayField === 'id' ? "$view | $edit" : "$edit";

            return $this->Html->tableCells(
                array_merge(array_map(function ($field) use ($controller, $displayField, $entity) {
                    return $field !== $displayField ? $this->display($entity->$field) :
                        $this->Html->link(__($entity->$displayField),
                            ['controller' => $controller, 'action' => 'view', $entity->id]);
                }, $visibleFields), [$actions])
            );
        }, $entities);

        return $this->_View->element('Skeleton.table', compact('thead', 'tbody', 'priority'));
    }
}
