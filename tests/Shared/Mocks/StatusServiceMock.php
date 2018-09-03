<?php


namespace Curator\Tests\Shared\Mocks;


use Curator\Status\StatusModel;
use Curator\Status\StatusService;

class StatusServiceMock extends StatusService
{
  protected $mock_status;

  public function __construct()
  {
    $this->mock_status = new StatusModel();
    $this->mock_status->site_root = '/app';
  }

  public function getStatus() {
    return $this->mock_status;
  }
}