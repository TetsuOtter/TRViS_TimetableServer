<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\repo\ColorsRepo;
use dev_t0r\trvis_backend\repo\ProjectsPrivilegesRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * ColorsService — Project-rooted CRUD for Color.
 *
 * (Re-rooted from work-group to Project: `colors.work_groups_id` -> `projects_id`.)
 * Privilege root is Project, resolved through ProjectsPrivilegesRepo.
 *
 * The `beforeUpdate` hook from MyServiceBase is inlined into update():
 * 'color_8bit' → red_8bit/green_8bit/blue_8bit
 * 'color_real' → red_real/green_real/blue_real
 *
 * Method signatures match the RED test (ColorApiTest):
 *   create($wgId, $userId, array $dataList): RetValueOrError<array<Color>>
 *   getOne($userId, $colorsId): RetValueOrError<Color>
 *   getPage($userId, $wgId, $page, $perPage, $topId): RetValueOrError<array<Color>>
 *   update($userId, $id, $data, array $kvpArray): RetValueOrError<Color>
 *   delete($userId, $id): RetValueOrError<null>
 */
final class ColorsService
{
	private readonly ColorsRepo $colorsRepo;
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	private const KEYS = ['name', 'description', 'color_8bit', 'color_real'];

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->colorsRepo = new ColorsRepo($db, $logger);
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<array<Color>>
	 */
	public function create(
		UuidInterface $projectsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(projectsId:{projectsId}, userId:{userId})",
			['projectsId' => $projectsId, 'userId' => $userId],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				Constants::HTTP_BAD_REQUEST,
				"Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errProjectNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertIdList = [];
		foreach ($dataList as $_d) {
			$insertIdList[] = Uuid::uuid7();
		}

		$this->db->beginTransaction();
		try {
			$insertResult = $this->colorsRepo->insertList(
				projectsId: $projectsId,
				owner: $userId,
				insertIdList: $insertIdList,
				dataList: $dataList,
				db: $this->db,
			);
			if ($insertResult->isError) {
				$this->db->rollBack();
				return $insertResult;
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create Color: {exception}",
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

		$selectResult = $this->colorsRepo->selectList($insertIdList);
		if ($selectResult->isError) {
			return $selectResult;
		}
		return RetValueOrError::withValue($selectResult->value, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<Color>
	 */
	public function getOne(
		string $userId,
		UuidInterface $colorsId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, colorsId:{colorsId})",
			['userId' => $userId, 'colorsId' => $colorsId],
		);

		$privResult = $this->colorsRepo->selectPrivilegeType(
			id: $colorsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return Utils::errContentNotFound();
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		return $this->colorsRepo->selectOne($colorsId);
	}

	/**
	 * @return RetValueOrError<array<Color>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $projectsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, projectsId:{projectsId}, page:{page})",
			['userId' => $userId, 'projectsId' => $projectsId, 'page' => $pageFrom1],
		);

		$privResult = $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return Utils::errContentNotFound();
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->colorsRepo->selectPageTotalCount(
			projectsId: $projectsId,
			topId: $topId,
		);
		$selectResult = $this->colorsRepo->selectPage(
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
	 * 4th arg is the raw kvp array (from Utils::getArrayForUpdateSource or
	 * directly from the test). The beforeUpdate hook decomposes color_8bit /
	 * color_real sub-objects into flat DB columns before calling the repo.
	 *
	 * @return RetValueOrError<Color>
	 */
	public function update(
		string $userId,
		UuidInterface $colorsId,
		object $data,
		array $kvpArray,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, colorsId:{colorsId})",
			['userId' => $userId, 'colorsId' => $colorsId],
		);

		$privResult = $this->colorsRepo->selectPrivilegeType(
			id: $colorsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return Utils::errContentNotFound();
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		// beforeUpdate: decompose nested sub-objects to flat DB columns
		$hasColor8bit = array_key_exists('color_8bit', $kvpArray);
		$hasColorReal = array_key_exists('color_real', $kvpArray);
		if ($hasColor8bit) {
			$color8bit = $kvpArray['color_8bit'];
			$kvpArray['red_8bit'] = $color8bit->red;
			$kvpArray['green_8bit'] = $color8bit->green;
			$kvpArray['blue_8bit'] = $color8bit->blue;
			unset($kvpArray['color_8bit']);
		}
		if ($hasColorReal) {
			$colorReal = $kvpArray['color_real'];
			$kvpArray['red_real'] = $colorReal->red;
			$kvpArray['green_real'] = $colorReal->green;
			$kvpArray['blue_real'] = $colorReal->blue;
			unset($kvpArray['color_real']);
		}

		$this->db->beginTransaction();
		try {
			$updateResult = $this->colorsRepo->update($colorsId, $kvpArray);
			if ($updateResult->isError) {
				$this->db->rollBack();
				return $updateResult;
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to update Color: {exception}",
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

		$selectResult = $this->colorsRepo->selectOne($colorsId);
		if ($selectResult->isError) {
			return $selectResult;
		}
		return $selectResult;
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $colorsId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, colorsId:{colorsId})",
			['userId' => $userId, 'colorsId' => $colorsId],
		);

		$privResult = $this->colorsRepo->selectPrivilegeType(
			id: $colorsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return Utils::errContentNotFound();
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$this->db->beginTransaction();
		try {
			$deleteResult = $this->colorsRepo->deleteOne($colorsId);
			if ($deleteResult->isError) {
				$this->db->rollBack();
				return $deleteResult;
			}
			$this->db->commit();
			return $deleteResult;
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to delete Color: {exception}",
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
