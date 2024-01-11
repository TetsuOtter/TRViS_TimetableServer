<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\repo\TimetableRowsRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyServiceBase<TimetableRow, TimetableRowsRepo, TrainsRepo>
 */
final class TimetableRowsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new TimetableRowsRepo($db, $logger),
			parentRepo: new TrainsRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'TimetableRow',
			keys: [
				'stations_id',
				'station_tracks_id',
				'colors_id_marker',
				'description',

				'drive_time_mm',
				'drive_time_ss',

				'is_operation_only_stop',
				'is_pass',
				'has_bracket',
				'is_last_stop',

				'arrive_time_hh',
				'arrive_time_mm',
				'arrive_time_ss',

				'departure_time_hh',
				'departure_time_mm',
				'departure_time_ss',

				'run_in_limit',
				'run_out_limit',

				'remarks',

				'arrive_str',
				'departure_str',

				'marker_text',

				'work_type'
			],
		);
	}
}
