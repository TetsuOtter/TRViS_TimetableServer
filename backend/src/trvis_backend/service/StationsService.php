<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<Station, StationsRepo>
 */
final class StationsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			targetRepo: new StationsRepo($db, $logger),
			parentRepo: new WorkGroupsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Station',
			keys: [
				'name',
				'description',
				'location_km',
				'location_lonlat',
				'on_station_detect_radius_m',
				'record_type',
			],
		);
	}
}
