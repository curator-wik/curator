<?php


namespace Curator\Tests\Functional;


use Curator\Batch\RunnerService;
use Curator\Persistence\PersistenceInterface;
use Curator\Status\StatusService;
use mbaynton\BatchFramework\Internal\FunctionWrappers;

class MockedTimeRunnerService extends RunnerService {
  public function __construct(\Curator\Persistence\PersistenceInterface $persistence, \Curator\Status\StatusService $status_service, FunctionWrappers $runner_svc_function_wrappers) {
    $this->time_source = $runner_svc_function_wrappers;
    parent::__construct($persistence, $status_service);
  }
}
