<?php

namespace Skeleton\Seed;

use Phinx\Db\Table;

abstract class AbstractSeed extends \Phinx\Seed\AbstractSeed
{

    /**
     * @param string|Table $table
     * @param array $data
     * E.g.
     *
     * [[ 'id' => 1 ], [ 'id' => 2 ]]
     * <br>Or<br>
     * [[ 'article_id' => 1, 'tag_id' => 1 ], [ 'article_id' => 1, 'tag_id' => 2 ]]
     */
    public function delete($table, $data)
    {
        // convert to table object
        if ($table instanceof Table) {
            $table = $table->getName();
        }

        if (empty($data) || !is_assoc($data[0])) {
            return;
        }

        $columns = implode(',', array_keys($data[0]));
        $values = implode(',', array_map(function ($entry) {
            return '(' . implode(',', array_values($entry)) . ')';
        }, $data));
        $this->execute("DELETE FROM $table WHERE ($columns) IN ($values)");
    }
}
