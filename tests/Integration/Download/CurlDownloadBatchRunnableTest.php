<?php


namespace Curator\Tests\Integration\Download;


use Curator\APIModel\v1\BatchRunnerRawProgressMessage;
use Curator\Download\CurlDownloadBatchRunnable;
use Curator\IntegrationConfig;
use Curator\Task\TaskInterface;
use Curator\Tests\Integration\WebserverRunnerTrait;
use mbaynton\BatchFramework\TaskInstanceStateInterface;

class CurlDownloadBatchRunnableTest extends \PHPUnit_Framework_TestCase {
  use WebserverRunnerTrait;

  protected static $random512 = '';

  /**
   * @var int $count
   * Used to vary the url, and thus the download file, to ensure
   * readbacks are really from the current test.
   */
  protected $count = 0;

  /**
   * @var TaskInterface $task
   */
  protected $task;

  /**
   * @var TaskInstanceStateInterface $taskState
   */
  protected $taskState;

  protected static function installDownloadData() {
    $fh = fopen('/dev/urandom', 'r');
    $random512 = fread($fh, 512 * 1024);
    fclose($fh);
    file_put_contents('/tmp/random512.dat', $random512);
    self::$random512 = $random512;
  }

  public function setUp() {
    parent::setUp();

    if (self::$h_server_proc === FALSE) {
      $this->fail('Test fails because development webserver is not operational.');
    }

    // At present, this single-step runner does not use the Task or TaskInstanceState,
    // so not bothering to really give them.
    $this->task = $this->getMockBuilder('\mbaynton\BatchFramework\TaskInterface')
      ->getMock();
    $this->taskState = $this->getMockBuilder('mbaynton\BatchFramework\TaskInstanceStateInterface')
      ->getMock();
  }

  protected function sutFactory($opts = []) {
    $url = isset($opts['url']) ? $opts['url'] : sprintf("%srandom512.dat?count=%d", getenv('TEST_HTTP_SERVER'), $this->count);

    $sut = new CurlDownloadBatchRunnable(
      IntegrationConfig::getNullConfig(),
      1,
      $url
    );
    $this->count++;
    return $sut;
  }

  public function testFileIsDownloadedToDisk() {
    $sut = $this->sutFactory();

    $file = $sut->run($this->task, $this->taskState);

    $this->assertEquals(
      self::$random512,
      file_get_contents($file),
      'Downloaded random data differed from the source copy.'
    );
  }

  /**
   * @expectedException \RuntimeException
   * @expectedExceptionMessage 404 Not Found
   */
  public function testFileDownloadFailureIsCorrectlyReported() {
    $sut = $this->sutFactory(['url' => getenv('TEST_HTTP_SERVER') . 'does/not/exist']);
    $file = $sut->run($this->task, $this->taskState);
  }

  /**
   * @expectedException \RuntimeException
   * @expectedExceptionMessage Connection refused
   */
  public function testFileDownloadFailureIsCorrectlyReported_2() {
    $sut = $this->sutFactory(['url' => 'http://localhost:8079/']);
    $file = $sut->run($this->task, $this->taskState);
  }

  public function testDownloadProgressIsReported() {
    $sut = $this->sutFactory();
    $called_counter = 0;
    $last_pct_reported = 0;
    $sut->setUpdateMessageCallback(function(BatchRunnerRawProgressMessage $m) use (&$called_counter, &$last_pct_reported) {
      $called_counter++;
      $this->assertGreaterThanOrEqual($last_pct_reported, $m->pct);
      $this->assertGreaterThanOrEqual(0, $m->pct);
      $this->assertLessThanOrEqual(100, $m->pct);
      $last_pct_reported = $m->pct;
    });

    $file = $sut->run($this->task, $this->taskState);

    // At least 2 calls expected: 1 after transfer completes, 1+ by cURL.
    $this->assertGreaterThan(1, $called_counter);
  }
}
