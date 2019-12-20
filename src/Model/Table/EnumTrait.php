<?php
declare(strict_types=1);

namespace Skeleton\Model\Table;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;

trait EnumTrait
{
    /**
     * @param string $field The enum field to read the options from.
     * @return array A list of available enum values by field
     */
    function getEnumOptions($field)
    {
        $table = $this->getTable();
        $cacheKey = "${table}_${field}_enum_options";
        $options = Cache::read($cacheKey);

        // fetch enums from the table schema
        if (!$options) {
            $sql = "SHOW COLUMNS FROM `$table` LIKE '$field'";
            $db = ConnectionManager::get($this->defaultConnectionName());
            $stmt = $db->query($sql)->fetch('assoc');
            $enumData = $stmt['Type'];

            $options = false;
            if (!empty($enumData)) {
                $patterns = ['enum(', ')', '\''];
                $enumData = str_replace($patterns, '', $enumData);
                $temp = explode(',', $enumData);
                foreach ($temp as $t) {
                    $options[$t] = $t;
                }
            }
            Cache::write($cacheKey, $options);
        }

        return $options;
    }
}
