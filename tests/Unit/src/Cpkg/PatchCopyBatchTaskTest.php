<?php


namespace Curator\Tests\Unit\Cpkg;


use Curator\Cpkg\PatchCopyBatchTask;

class PatchCopyBatchTaskTest extends \PHPUnit\Framework\TestCase {

  public function testStartEndRunnableId_NoRunnables() {
    $num_runners = 4;
    $state_stub = $this->getMockBuilder('\mbaynton\BatchFramework\TaskInstanceStateInterface')->getMock();
    $state_stub->method('getNumRunnables')->willReturn(0);
    $state_stub->method('getNumRunners')->willReturn($num_runners);

    for($i = 0; $i < $num_runners; $i++) {
      list($start, $end) = PatchCopyBatchTask::getStartEndRunnableId($state_stub, $i);
      $this->assertEquals(
        0,
        $start,
        "Start for runner $i"
      );
      $this->assertEquals(
        -1,
        $end,
        "End for runner $i"
      );
    }
  }

  protected function assertAllRunnablesExecuted($num_runnables, $runner_bounds) {
    usort($runner_bounds, function($a, $b) {
      return $a[0] - $b[0];
    });

    $this->assertEquals(
      0,
      $runner_bounds[0][0],
      'First executed runnable is ID 0'
    );

    $last_runner_stop = $runner_bounds[0][1];
    foreach ($runner_bounds as $runner_rank => $current_runner_bounds) {
      if ($runner_rank == 0) continue;
      $last_runner_rank = $runner_rank - 1;
      $this->assertEquals(
        $last_runner_stop + 1,
        $current_runner_bounds[0],
        "Runner rank $runner_rank starts at the runnable id after runner rank $last_runner_rank."
      );
      $last_runner_stop = $current_runner_bounds[1];
    }

    $this->assertEquals(
      $num_runnables - 1, // 0-based IDs
      $last_runner_stop,
      'Last runnable ID that would be executed does not correspond to runnable count.'
    );
  }

  protected function _testRunnableCountRunnerCountPermutation($runner_count, $runnable_count) {
    $state_stub = $this->getMockBuilder('\mbaynton\BatchFramework\TaskInstanceStateInterface')->getMock();
    $state_stub->method('getNumRunnables')->willReturn($runnable_count);
    $state_stub->method('getNumRunners')->willReturn($runner_count);

    $runner_bounds = [];
    for($i = 0; $i < $runner_count; $i++) {
      $runner_bounds[$i] = PatchCopyBatchTask::getStartEndRunnableId($state_stub, $i);
    }
    return $runner_bounds;
  }

  public function testStartEndRunnableId_RunnablesEqualsRunners() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 4);
    $this->assertAllRunnablesExecuted(4, $runner_bounds);
  }

  public function testStartEndRunnableId_RunnablesEqualsRunnersPlus1() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 5);
    $this->assertAllRunnablesExecuted(5, $runner_bounds);
  }

  public function testStartEndRunnableId_RunnablesEqualsRunnersPlus2() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 6);
    $this->assertAllRunnablesExecuted(6, $runner_bounds);
  }

  public function testStartEndRunnableId_RunnablesEqualsRunnersPlus3() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 7);
    $this->assertAllRunnablesExecuted(7, $runner_bounds);
  }

  public function testStartEndRunnableId_RunnablesEquals2xRunners() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 8);
    $this->assertAllRunnablesExecuted(8, $runner_bounds);
  }

  public function testStartEndRunnableId_RunnablesEquals2xRunnersPlus2() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 10);
    $this->assertAllRunnablesExecuted(10, $runner_bounds);
  }

  public function testStartEndRunnableId_OneRunnable() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(3, 1);
    $this->assertEquals(
      [
        [0, -1],
        [0, -1],
        [0, 0]
      ],
      $runner_bounds
    );
  }

  public function testStartEndRunnableId_TwoRunnables() {
    $runner_bounds = $this->_testRunnableCountRunnerCountPermutation(4, 2);
    $this->assertEquals(
      [
        [0, -1],
        [0, -1],
        [0, 0],
        [1, 1]
      ],
      $runner_bounds
    );
  }

}
