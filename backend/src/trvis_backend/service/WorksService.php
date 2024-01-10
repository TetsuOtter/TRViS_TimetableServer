<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\Work;
use dev_t0r\trvis_backend\repo\WorkGroupsPrivilegesRepo;
use dev_t0r\trvis_backend\repo\WorksRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IMyServiceBase<Work, WorksRepo>
 */
final class WorksService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			targetRepo: new WorksRepo($db, $logger),
			parentRepo: new WorkGroupsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Work',
			keys: [
				'description',
				'name',
				'affect_date',
				'affix_content_type',
				'remarks',
				'has_e_train_timetable',
				'e_train_timetable_content_type',
			],
		);
	}
}
