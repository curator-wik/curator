<?php


namespace Curator\Tests\Functional\Authorization;


use Curator\Tests\Functional\Authorization\InstallationAgeMock;
use Curator\Tests\Functional\Util\Session;
use Curator\Tests\Functional\WebTestCase;
use Curator\Tests\Shared\Mocks\InMemoryPersistenceMock;
use Curator\Authorization\AuthorizationMiddleware;

class AuthorizationMiddlewareStandaloneTest extends WebTestCase  {

  const ENDPOINT_ALLOWS_UNCONFIGURED = '/api/v1/batch/runner';

  public function __construct() {
    parent::__construct(TRUE);
  }

  public function createApplication() {
    return parent::doCreateApplication(FALSE);
  }

  protected function installationAgeTest($simulated_age) {
    /**
     * @var InMemoryPersistenceMock $persistence
     */
    $persistence = $this->app['persistence'];
    // Make the application unconfigured as in a new installation
    $persistence->clear();
    // Make sure the request is not authenticated
    Session::makeSessionUnauthenticated($this->app['session']);

    // Mock the usual source of installation time
    $this->app['authorization.installation_age'] = function() use($simulated_age) {
      return new InstallationAgeMock($simulated_age);
    };

    $client = self::createClient();
    $client->request('POST', self::ENDPOINT_ALLOWS_UNCONFIGURED,
      [],
      [],
      [
        'HTTP_X-Runner-Id' => 1,
      ]);
    return $client;
  }

  /**
   * @expectedException \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @expectedExceptionMessage timely fashion
   */
  public function testNeglectedInstallationDeniesAccess() {
    // Verifies that all endpoints on a new installation stop allowing access
    // after N hours of neglecting to configure authentication.
    $this->installationAgeTest(time() - (AuthorizationMiddleware::UNCONFIGURED_NEGLECT_HOURS * 60 * 60) - 1);
  }

  /**
  * @expectedException \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
  */
  public function testAgeReadErrorDeniesAccess() {
    // Simulate error obtaining installation ctime/mtime
    $this->installationAgeTest(FALSE);
  }

  public function testNewInstallationAllowsAccess() {
    $client = $this->installationAgeTest(time());
    $this->assertEquals(
      200,
      $client->getResponse()->getStatusCode()
    );
  }

  public function testFutureInstallationAllowsAccess() {
    // E.g. if the file was written by a system with a different clock,
    // or the clock was adjusted. If we did not allow access until some future
    // window, it would be virtually impossible to secure the installation.
    $client = $this->installationAgeTest(time() + 25 * 60 * 60);
    $this->assertEquals(
      200,
      $client->getResponse()->getStatusCode()
    );
  }

}
