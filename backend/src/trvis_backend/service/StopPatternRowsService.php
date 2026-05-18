<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\StopPatternRow;
use dev_t0r\trvis_backend\repo\StopPatternRowsRepo;
use dev_t0r\trvis_backend\repo\StopPatternsRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * StopPatternRowは StopPattern 直下。create/getList の親権限チェックは
 * StopPatternsRepo::selectPrivilegeType (project-rooted override,
 * parentId=stopPatternId から stop_patterns.projects_id を解決して委譲)。
 *
 * @template-implements IMyServiceBase<StopPatternRow, StopPatternRowsRepo>
 */
final class StopPatternRowsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new StopPatternRowsRepo($db, $logger),
			parentRepo: new StopPatternsRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'StopPatternRow',
			keys: [
				'project_stations_id',
				'sort_key',
				'track_name',
				'track_hidden',
				'is_operation_only_stop',
				'is_pass',
				'drive_time_mm',
				'drive_time_ss',
				'dwell_time_mm',
				'dwell_time_ss',
				'show_arrive',
				'show_departure',
				'arrive_str',
				'departure_str',
				'run_in_limit',
				'run_out_limit',
				'remarks',
				'always_show_hh',
			],
		);
	}
}
