<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\repo\StationsOnLineRepo;
use dev_t0r\trvis_backend\repo\LineRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * StationOnLineは Line 直下。create/getList の親権限チェックは
 * LineRepo::selectPrivilegeType (project-rooted override, parentId=lineId
 * から project_lines.projects_id を解決して委譲)。
 * getOne/update/delete は StationsOnLineRepo::selectPrivilegeType。
 *
 * @template-implements IMyServiceBase<StationOnLine, StationsOnLineRepo>
 */
final class StationsOnLineService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new StationsOnLineRepo($db, $logger),
			parentRepo: new LineRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'StationOnLine',
			keys: [
				'project_stations_id',
				'location_m',
				'location_lonlat',
				'track_hidden_by_default',
			],
		);
	}
}
