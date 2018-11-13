<?php


namespace Curator\Tests\Functional\APIController\v1\Batch;


use Curator\APIController\v1\Batch\BatchRunnerController;
use Curator\CuratorApplication;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Curator\Tests\Unit\CuratorApplicationTest;

class BatchRunnerControllerTest extends WebTestCase {

  function setUp() {
    parent::setUp();

    // Almost all tests presume an authenticated session.
    Session::makeSessionAuthenticated($this->app['session']);
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  function testUnsolicitedRequestErrors() {
    $client = static::createClient();
    $client->request('POST', '/api/v1/batch/runner');
  }

  function testTaskInfoRequestWhenNoTaskGroups() {
    $client = static::createClient();
    $client->request('GET', '/api/v1/batch/current-task');
    $json = json_decode($client->getResponse()->getContent());

    $this->assertCount(0, $json->runnerIds, 'More than 0 runner IDs reported ready, but there should be no scheduled tasks.');
  }

  function todo_testStolenBatchIsSkipped() {
    /**
     * @var CuratorApplication $app
     */
    $app = $this->app;

  }
}
