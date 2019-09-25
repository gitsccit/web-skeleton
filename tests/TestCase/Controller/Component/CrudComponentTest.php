<?php

namespace Skeleton\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\TestSuite\TestCase;
use Skeleton\Controller\Component\CrudComponent;

/**
 * Skeleton\Controller\Component\CrudComponent Test Case
 */
class CrudComponentTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Skeleton\Controller\Component\CrudComponent
     */
    public $Crud;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $registry = new ComponentRegistry();
        $this->Crud = new CrudComponent($registry);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Crud);

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

    /*  public function testPaginateAssociations()
      {
          $object = TableRegistry::getTableLocator()->get('Users')->find()->contain(['Roles']);
          $result = $this->Crud->paginateAssociations($object);

          $this->assertCount(2, $result);
          $this->assertArrayHasKey('associations', $result);
      }*/
}
