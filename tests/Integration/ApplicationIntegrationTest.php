<?php


namespace Curator\Tests\Integration;


use Curator\AppManager;
use Curator\IntegrationConfig;
use Symfony\Component\HttpKernel\Client;

class ApplicationIntegrationTest extends \PHPUnit\Framework\TestCase {
  /*
   * The challenge with WebTestCase is it creates one HttpKernelInterface
   * instance and reuses it for all requests, whereas this test is specificially
   * to ensure that kernels constructed to trust all incoming requests (pre-
   * authorized) do so, and that they issue sessions which regularly
   * constructed kernels recognize as authorized.
   */
  const MODE_PREAUTHORIZED = 1;
  const MODE_DIRECT_ACCESS = 2;

  public function createApplication($mode) {
    // As with integration scripts, start with an AppManager.
    /**
     * @var AppManager $appManager
     */
    $appManager = include __DIR__ . '/../../dist/web/index.php';

    if ($mode === self::MODE_PREAUTHORIZED) {
      // Create and apply an IntegrationConfig. This behavior implies the
      // integration script has determined we should regard the user as authz'd.
      return $appManager->applyIntegrationConfig(
        IntegrationConfig::getNullConfig()
      );
    } else {
      // Simulate the behavior of a direct access script, which calls run().
      // We call prepareToRun() so that the kernel is merely returned.
      return $appManager->prepareToRun();
    }
  }

  public function testExampleAdjoiningApplicationIntegration() {
    $preauthorizedKernel = $this->createApplication(self::MODE_PREAUTHORIZED);

    $client = new Client($preauthorizedKernel);

    $client->request('POST', '/api/v1/batch/runner',
      [], [], ['HTTP_X-Runner-Id' => 1]
    );
    $response1 = $client->getResponse();
    $this->assertEquals(200, $response1->getStatusCode(), 'Request to kernel in preauthorized mode was not accepted.');

    // Simulate a subsequent process calling AppManager::run().
    $regularKernel = $this->createApplication(self::MODE_DIRECT_ACCESS);
    $client2 = new Client(
      $regularKernel, [], null, $client->getCookieJar()
    );

    $client2->request('POST', '/api/v1/batch/runner',
      [], [], ['HTTP_X-Runner-Id' => 1]
    );
    $response2 = $client->getResponse();
    $this->assertEquals(200, $response2->getStatusCode(), 'Request to kernel in direct access mode was not accepted.');
  }
}
