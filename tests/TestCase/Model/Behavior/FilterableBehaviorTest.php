<?php
declare(strict_types=1);

namespace Skeleton\Test\TestCase\Model\Behavior;

use Skeleton\Model\Behavior\FilterableBehavior;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Behavior\FilterableBehavior Test Case
 */
class FilterableBehaviorTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Skeleton\Model\Behavior\FilterableBehavior
     */
    protected $Filterable;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $table = new Table();
        $this->Filterable = new FilterableBehavior($table);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Filterable);

        parent::tearDown();
    }
}
