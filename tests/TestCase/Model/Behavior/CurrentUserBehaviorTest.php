<?php

namespace Skeleton\Test\TestCase\Model\Behavior;

use Cake\TestSuite\TestCase;
use Skeleton\Model\Behavior\CurrentUserBehavior;

/**
 * Skeleton\Model\Behavior\CurrentUserBehavior Test Case
 */
class CurrentUserBehaviorTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Skeleton\Model\Behavior\CurrentUserBehavior
     */
    public $CurrentUser;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->CurrentUser = new CurrentUserBehavior();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->CurrentUser);

        parent::tearDown();
    }

    /**
     * Test initial setup
     *
     * @return void
     */
    public function testInitialization()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
