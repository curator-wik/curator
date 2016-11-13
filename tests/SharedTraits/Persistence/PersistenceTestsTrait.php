<?php


namespace Curator\Tests\SharedTraits\Persistence;


use Curator\Persistence\PersistenceInterface;

trait PersistenceTestsTrait {
  /**
   * @return PersistenceInterface
   */
  protected abstract function sutFactory();

  public function testBasicStoreAndRetrieve() {
    $sut = $this->sutFactory();
    $sut->beginReadWrite();
    $sut->set('test', 'hello world');
    $this->assertEquals('hello world', $sut->get('test'), 'Basic store/retrieve failed.');
    $sut->end();

    $sut->beginReadOnly();
    $this->assertEquals('hello world', $sut->get('test'), 'Basic store/retrieve after end()ing writes failed.');
    $sut->end();
  }

  public function testGetDefaultValue() {
    $sut = $this->sutFactory();
    $sut->beginReadOnly();
    $this->assertEquals('the default', $sut->get('testGetDefaultValue', 'the default'), 'Default value was not returned when retrieving an unset key.');
  }

  public function testUnsetValue() {
    $sut = $this->sutFactory();
    $sut->beginReadWrite();
    $sut->set('testUnsetValue', 'set');
    $this->assertEquals('set', $sut->get('testUnsetValue'));
    $sut->set('testUnsetValue', NULL);
    $this->assertNull($sut->get('testUnsetValue'));

    $sut->set('testUnsetValue', 'set');
    $sut->end();

    $sut->beginReadWrite();
    $sut->set('testUnsetValue', NULL);
    $sut->end();

    $sut->beginReadOnly();
    $this->assertEquals('unset', $sut->get('testUnsetValue', 'unset'));
    $sut->end();
  }

  /**
   * @expectedException \LogicException
   */
  public function testGetWithoutLock_throws() {
    $sut = $this->sutFactory();
    $sut->get('foo');
  }

  /**
   * @expectedException \LogicException
   */
  public function testGetWithoutLock_throws_2() {
    $sut = $this->sutFactory();
    $sut->beginReadOnly();
    $sut->end();
    $sut->get('foo');
  }

  /**
   * @expectedException \LogicException
   */
  public function testSetWithoutLock_throws() {
    $sut = $this->sutFactory();
    $sut->set('foo', 'bar');
  }

  /**
   * @expectedException \LogicException
   */
  public function testSetWithoutLock_throws2() {
    $sut = $this->sutFactory();
    $sut->beginReadWrite();
    $sut->end();
    $sut->set('foo', 'bar');
  }

}
