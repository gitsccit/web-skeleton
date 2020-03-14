<?php
declare(strict_types=1);

namespace Skeleton\View\Helper;

use Cake\Datasource\EntityInterface;
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
    public $helpers = ['Form', 'Html'];
    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [];

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
            [$plugin,] = pluginSplit($table->getRegistryAlias());
            $prefix = method_exists($table, 'getPrefix') ? $table->getPrefix() : '';
            $value = $this->Html->link(
                __($value->{$table->getDisplayField()}),
                ['controller' => $table->getTable(), 'action' => 'view', $value->id, 'prefix' => $prefix, 'plugin' => $plugin]
            );
        } elseif ($value instanceof \DateTimeInterface) {
            $timezone = $this->_View->getRequest()->getSession()->read('Auth.User.time_zone.name');
            $value = $this->_View->Time->format($value, null, null, $timezone);
        } elseif (empty($value)) {
            $value = "—";
        } elseif (is_string($value)) {
            // add word break after '@', so long email addresses will wrap
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $value = str_replace('@', '@<wbr>', $value);
            }
            $value = __($value);
        }

        return $value;
    }
}
