<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<StationTrack, StationTracksRepo>
 */
final class StationTracksService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			targetRepo: new StationTracksRepo($db, $logger),
			parentRepo: new StationsRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Station',
			keys: [
				'name',
				'description',
				'run_in_limit',
				'run_out_limit',
			],
		);
	}
}
