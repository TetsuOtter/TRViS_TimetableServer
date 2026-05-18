<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\Line;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use PDO;
use Psr\Log\LoggerInterface;

/**
 * Lineは Project 直下。create/getList の親権限チェックは
 * ProjectsPrivilegesRepo (parentId = projectId)。
 * getOne/update/delete は LineRepo::selectPrivilegeType (project-rooted override)。
 *
 * @template-implements IMyServiceBase<Line, LineRepo>
 */
final class LineService extends MyServiceBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			targetRepo: new LineRepo($db, $logger),
			parentRepo: new ProjectsPrivilegesRepo($db, $logger),
			logger: $logger,
			dataTypeName: 'Line',
			keys: [
				'name',
				'description',
			],
		);
	}
}
