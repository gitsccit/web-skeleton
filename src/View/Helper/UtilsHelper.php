<?php
declare(strict_types=1);

namespace Skeleton\View\Helper;

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\View\Helper;

/**
 * Utils helper
 *
 * @property \Cake\View\Helper\FormHelper $Form
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\TextHelper $Text
 */
class UtilsHelper extends Helper
{
    public array $helpers = ['Form', 'Html', 'Text'];
    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $_defaultConfig = [];

    /**
     * @param mixed $value
     * @return string Parsed string.
     */
    public function display($value, $maxLength = 50)
    {
        if (is_numeric($value) && !is_string($value)) {
            $value = (string)$value;
        } elseif (is_bool($value)) {
            $value = $value ? __('Yes') : __('No');
        } elseif ($value instanceof EntityInterface) {
            $table = TableRegistry::getTableLocator()->get($value->getSource());
            [$plugin,] = pluginSplit($table->getRegistryAlias());
            $prefix = $this->_View->getRequest()->getParam('prefix');
            $controller = Inflector::camelize($table->getTable());
            if (!class_exists("App\Controller\\$prefix\\{$controller}Controller")) {
                $prefixes = get_subfolder_names(APP . 'Controller/*');
                $prefix = array_filter($prefixes, function ($prefix) use ($controller) {
                    return class_exists("App\Controller\\$prefix\\{$controller}Controller");
                })[0] ?? null;
            }
            $value = $this->Html->link(
                __($value->{$table->getDisplayField()}),
                ['controller' => $controller, 'action' => 'view', $value->id, 'prefix' => $prefix, 'plugin' => $plugin]
            );
        } elseif ($value instanceof \DateTimeInterface) {
            $timezone = $this->_View->getRequest()->getSession()->read('Auth.User.time_zone.name');
            $value = $this->_View->Time->format($value, null, false, $timezone);
        } elseif (empty($value)) {
            $value = "—";
        } elseif (is_string($value)) {
            $json = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $json = array_is_list($json) ? $json : [$json];
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $id = random_string(16);
                $value = "<a href='javascrip(0)' data-bs-toggle='modal' data-bs-target='#$id'>View</a>

<div class='modal fade' id='$id' data-bs-keyboard='false' tabindex='-1'
     aria-labelledby='{$id}Label' aria-hidden='true'>
    <div class='modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable'>
        <div class='modal-content'>
            <div class='modal-header'>
                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
            </div>
            <div class='modal-body'>
                <table class='table table-bordered'>
                    {$this->Html->tableHeaders(array_keys($json[0]))}
                    {$this->Html->tableCells($json)}
                </table>
            </div>
        </div>
    </div>
</div>
";
                return $value;
            }
            // add word break after '@', so long email addresses will wrap
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $value = str_replace('@', '@<wbr>', $value);
            }
            $value = __($value);
            $value = $this->Text->truncate($value, $maxLength);
        }

        return $value;
    }
}
