<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\ProjectStation;
use dev_t0r\trvis_backend\repo\ProjectStationsRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<ProjectStation, ProjectStationsRepo>
 */
final class ProjectStationsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new ProjectStationsRepo($db, $logger),
			parentRepo: new ProjectsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'ProjectStation',
			keys: [
				'name',
				'full_name',
				'location_lonlat',
				'on_station_detect_radius_m',
				'always_show_hh',
			],
		);
	}
}
