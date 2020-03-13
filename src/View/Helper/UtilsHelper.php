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
                __($value->{$table->getDisplayField()}),
                ['controller' => $table->getTable(), 'action' => 'view', $value->id, 'plugin' => $plugin]
            );
        } elseif ($value instanceof \DateTimeInterface) {
            $timezone = $this->_View->getRequest()->getSession()->read('Auth.User.time_zone.name');
            $value = $this->_View->Time->format($value, null, null, $timezone);
        } elseif (empty($value)) {
            $value = "—";
        } elseif (is_string($value) && $value === strip_tags($value)) {
            $value = __(h($value));
        }

        return $value;
    }
}
