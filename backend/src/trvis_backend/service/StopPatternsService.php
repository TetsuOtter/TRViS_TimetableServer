<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\StopPattern;
use dev_t0r\trvis_backend\repo\StopPatternsRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<StopPattern, StopPatternsRepo>
 */
final class StopPatternsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new StopPatternsRepo($db, $logger),
			parentRepo: new ProjectsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'StopPattern',
			keys: [
				'lines_id',
				'name',
				'from_project_stations_id',
				'to_project_stations_id',
				'direction',
			],
		);
	}
}
