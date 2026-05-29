<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\LineRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Service for Line. Privilege root is **direct via projects_id**:
 * - create / getPage use ProjectsPrivilegesRepo keyed on projectsId.
 * - getOne / update / delete use LineRepo::selectPrivilegeType keyed on lineId
 *   (which joins project_lines → projects_privileges internally).
 *
 * Write-access contract (unified privilege-error rule): the caller must have
 * at least `write` to mutate. A read-capable member who lacks `write` gets
 * 403 (errForbidden) — they can see the resource, so 404 would be a lie. A
 * non-member (no privilege row) is still caught earlier by
 * selectPrivilegeType's isError → errProjectNotFound / errLineNotFound (404),
 * so existence is never disclosed to outsiders.
 */
final class LineService
{
	private readonly LineRepo $lineRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->lineRepo = new LineRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	/**
	 * Create a new Line under $projectsId.
	 *
	 * Privilege check: caller must have at least WRITE on the project.
	 * read-only members → 403; non-members → 404 (see class docblock).
	 *
	 * @return RetValueOrError<Line>
	 */
	public function create(
		UuidInterface $projectsId,
		string $userId,
		string $name,
		string $description,
	): RetValueOrError {
		$this->logger->debug(
			"createLine(projectsId:{projectsId}, userId:{userId})",
			['projectsId' => $projectsId, 'userId' => $userId],
		);

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			// read-capable member, lacks write → 403 (non-members already 404 via isError above)
			return Utils::errForbidden();
		}

		$lineId = Uuid::uuid7();
		$insertResult = $this->lineRepo->insertLine(
			lineId: $lineId,
			projectsId: $projectsId,
			owner: $userId,
			description: $description,
			name: $name,
		);
		if ($insertResult->isError) {
			return $insertResult;
		}

		$selectResult = $this->lineRepo->selectLineOne($userId, $lineId);
		if ($selectResult->isError) {
			return $selectResult;
		}
		return RetValueOrError::withValue($selectResult->value, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<Line>
	 */
	public function getOne(
		string $userId,
		UuidInterface $lineId,
	): RetValueOrError {
		$this->logger->debug(
			"getLine(userId:{userId}, lineId:{lineId})",
			['userId' => $userId, 'lineId' => $lineId],
		);
		return $this->lineRepo->selectLineOne($userId, $lineId);
	}

	/**
	 * @return RetValueOrError<array<Line>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $projectsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getLinePage(userId:{userId}, projectsId:{projectsId}, page:{page})",
			['userId' => $userId, 'projectsId' => $projectsId, 'page' => $pageFrom1],
		);

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->lineRepo->selectLinePageTotalCount(
			userId: $userId,
			projectsId: $projectsId,
			topId: $topId,
		);
		$selectResult = $this->lineRepo->selectLinePage(
			userId: $userId,
			projectsId: $projectsId,
			pageFrom1: $pageFrom1,
			perPage: $perPage,
			topId: $topId,
		);

		return RetValueOrError::withTotalCount(
			value: $selectResult,
			totalCount: $totalCountResult,
		);
	}

	/**
	 * Update a Line. Caller must have at least WRITE on the owning project.
	 * read-only members → 403; non-members → 404 (see class docblock).
	 *
	 * $patch is the model object carrying new values; $keys lists which keys
	 * are present in the request body (only those are updated).
	 *
	 * @return RetValueOrError<Line>
	 */
	public function update(
		string $userId,
		UuidInterface $lineId,
		object $patch,
		array $keys,
	): RetValueOrError {
		$this->logger->debug(
			"updateLine(userId:{userId}, lineId:{lineId})",
			['userId' => $userId, 'lineId' => $lineId],
		);

		$privResult = $this->lineRepo->selectPrivilegeType(
			id: $lineId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			// read-capable member, lacks write → 403 (non-members already 404 via isError above)
			return Utils::errForbidden();
		}

		$name = in_array('name', $keys, true) ? $patch->name : null;
		$description = in_array('description', $keys, true) ? $patch->description : null;

		$updateResult = $this->lineRepo->updateLine(
			lineId: $lineId,
			description: $description,
			name: $name,
		);
		if ($updateResult->isError) {
			return $updateResult;
		}

		return $this->lineRepo->selectLineOne($userId, $lineId);
	}

	/**
	 * Delete a Line. Caller must have at least WRITE on the owning project.
	 * read-only members → 403; non-members → 404 (see class docblock).
	 *
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $lineId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteLine(userId:{userId}, lineId:{lineId})",
			['userId' => $userId, 'lineId' => $lineId],
		);

		$privResult = $this->lineRepo->selectPrivilegeType(
			id: $lineId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			// read-capable member, lacks write → 403 (non-members already 404 via isError above)
			return Utils::errForbidden();
		}

		$this->db->beginTransaction();
		try {
			$now = Utils::getUtcNow();
			$deleteResult = $this->lineRepo->deleteLine(lineId: $lineId, deletedAt: $now);
			if ($deleteResult->isError) {
				$this->db->rollBack();
				return $deleteResult;
			}

			$this->db->commit();
			$this->logger->info(
				"deleteLine({lineId}) by user:'{userId}' success",
				['lineId' => $lineId, 'userId' => $userId],
			);
			return RetValueOrError::withValue(null);
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to delete line: {exception}",
				['exception' => $e],
			);
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'unknown error',
				$e->getCode(),
			);
		}
	}
}
