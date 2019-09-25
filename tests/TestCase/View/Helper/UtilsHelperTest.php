<?php
namespace Skeleton\Test\TestCase\View\Helper;

use Cake\TestSuite\TestCase;
use Cake\View\View;
use Skeleton\View\Helper\UtilsHelper;

/**
 * Skeleton\View\Helper\UtilsHelper Test Case
 */
class UtilsHelperTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \Skeleton\View\Helper\UtilsHelper
     */
    public $Utils;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $view = new View();
        $this->Utils = new UtilsHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Utils);

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
