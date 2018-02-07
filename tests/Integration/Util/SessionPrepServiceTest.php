<?php


namespace Curator\Tests\Integration\Util;


use Curator\Tests\Integration\WebserverRunnerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Class SessionPrepServiceTest
 */
class SessionPrepServiceTest extends \PHPUnit_Framework_TestCase {
  use WebserverRunnerTrait;

  protected static function getPhpServerDocroot() {
    return '/curator';
  }

  protected static function getSampleFilePath() { return '/tests/Integration/fixtures/adjoining_application.php'; }

  public function setUp() {
    parent::setUp();

    if (self::$h_server_proc === FALSE) {
      $this->fail('Test fails because development webserver is not operational.');
    }
  }

  protected function getClient() {
    $cookieJar = new CookieJar();

    $client = new Client([
      'base_url' => getenv('TEST_HTTP_SERVER'),
      'defaults' => [
        'cookies' => $cookieJar
      ]
    ]);

    return [$client, $cookieJar];
  }

  protected function getCookiesArray(CookieJar $cookieJar) {
    $cookies = $cookieJar->toArray();
    $out = [];
    foreach ($cookies as $cookie) {
      $out[$cookie['Name']] = $cookie;
    }

    return $out;
  }

  public function testAdjoiningAppWorks() {
    list($client, $cookieJar) = $this->getClient();
    $result = $client->get('/tests/Integration/fixtures/adjoining_application.php');
    $this->assertEquals(
      'This is the killer app!',
      $result->getBody(),
      'Baseline sanity check of simulated adjoining app failed.'
    );
  }

  public function testNewCuratorSession() {
    /**
     * @var \GuzzleHttp\Client $client
     * @var CookieJar $cookieJar
     */
    list($client, $cookieJar) = $this->getClient();

    // "log in", to simulate the adjoining app needing its own session.
    $result = $client->get('/tests/Integration/fixtures/adjoining_application.php?action=login');
    $this->assertEquals(
      'Logged in.',
      $result->getBody()
    );
    $cookies = $this->getCookiesArray($cookieJar);
    $this->assertArrayHasKey(
      'TESTAPP',
      $cookies
    );
    $adjoiningAppSessionId = $cookies['TESTAPP']['Value'];

    // Start Curator. This should result in a new session cookie, but the
    // adjoining app's session id should be unmodified.
    $result = $client->get('/tests/Integration/fixtures/adjoining_application.php?action=startCurator');
    $cookies = $this->getCookiesArray($cookieJar);

    $this->assertArrayHasKey(
      'TESTAPP',
      $cookies
    );
    $this->assertEquals($adjoiningAppSessionId, $cookies['TESTAPP']['Value']);

    $curatorSessionName = false;
    foreach ($cookies as $key => $cookie) {
      if (strncmp($key, 'CURATOR_', 8) === 0) {
        $curatorSessionName = $key;
        break;
      }
    }

    $this->assertNotFalse($curatorSessionName, 'Curator session cookie was not set.');
    $this->assertNotEquals($adjoiningAppSessionId, $cookies[$curatorSessionName]['Value']);
  }
}
