<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use PDO;
use Psr\Log\LoggerInterface;

final class StationsService
{
	private readonly WorkGroupsPrivilegesRepo $workGroupsPrivilegesRepo;
	private readonly StationsRepo $stationsRepo;
	public function __construct(
		private PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->workGroupsPrivilegesRepo = new WorkGroupsPrivilegesRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
	}
}
