<?php

namespace tests\core;

use API\Core;

require_once __DIR__ . "/../../core/Loader.php";

class Loader extends \PHPUnit_Framework_TestCase
{
  protected function setUp()
  {
    Core\Loader::getInstance()->reg();
  }

  public function testSingleton()
  {
    $loader1 = Core\Loader::getInstance();
    $loader2 = Core\Loader::getInstance();
    $this->assertTrue($loader1 === $loader2);
  }

  public function testSimpleClass()
  {
    $classA = new Loader\A();
    $this->assertEquals($classA->foo(), 'A');
    $classB = new Loader\B();
    $this->assertEquals($classB->foo(), 'B');
  }

  public function testNotFound()
  {
    try {
      new Core\Abracadabra();
      $this->assertTrue(false, 'Should be exception thrown');
    } catch (\API\Core\LoaderException $e) {
      $this->assertTrue(true);
    }
  }

  protected function tearDown()
  {
    Core\Loader::getInstance()->unreg();
  }
}
