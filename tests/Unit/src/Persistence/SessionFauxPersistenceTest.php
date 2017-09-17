<?php


namespace Curator\Tests\Unit\Persistence;


use Curator\Persistence\SessionFauxPersistence;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SessionFauxPersistenceTest extends \PHPUnit_Framework_TestCase {
  public function testSessionFauxPersistence() {
    $sut = new SessionFauxPersistence(new Session(new MockArraySessionStorage()));

    $this->assertEquals('x', $sut->get('test', 'x'));
    $sut->set('test', 'fred');
    $this->assertEquals('fred', $sut->get('test', 'x'));
    $sut->set('test', NULL);
    $this->assertEquals('x', $sut->get('test', 'x'));
  }

  public function testGetAll() {
    $sut = new SessionFauxPersistence(new Session(new MockArraySessionStorage()));

    $empty = $sut->getAll();
    $this->assertInternalType('array', $empty);
    $this->assertEmpty($empty);

    $sut->set('test one', 'foo');
    $sut->set('test two', 'bar');

    $all = $sut->getAll();
    $this->assertCount(2, $all);
    $this->assertArraySubset([
      'test one' => 'foo',
      'test two' => 'bar'
    ], $all, TRUE);
  }
}
