<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\Train;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<Train, TrainsRepo>
 */
final class TrainsService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new TrainsRepo($db, $logger),
			parentRepo: new WorksRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Train',
			keys: [
				'train_number',
				'description',
				'max_speed',
				'speed_type',
				'nominal_tractive_capacity',
				'car_count',
				'destination',
				'begin_remarks',
				'after_remarks',
				'remarks',
				'before_remarks',
				'after_remarks',
				'train_info',
				'direction',
				'day_count',
				'is_ride_on_moving',
			],
		);
	}
}
