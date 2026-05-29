<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Color;
use dev_t0r\trvis_backend\model\Color8bit;
use dev_t0r\trvis_backend\model\ColorReal;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * ColorsRepo — Project-scoped CRUD for the `colors` table.
 *
 * (Re-rooted from work-group to Project: `colors.work_groups_id` -> `projects_id`.)
 *
 * Implements IMyRepoSelectPrivilegeType: selectPrivilegeType resolves
 * the projects_id for the given colors_id, then delegates to
 * ProjectsPrivilegesRepo.
 *
 * No leading `_` on methods.
 */
final class ColorsRepo implements IMyRepoSelectPrivilegeType
{
	private readonly ProjectsPrivilegesRepo $projectsPrivilegesRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->projectsPrivilegesRepo = new ProjectsPrivilegesRepo($db, $logger);
	}

	private static function fetchResultToColor(mixed $d): Color
	{
		$color8bit = new Color8bit();
		$color8bit->setData([
			'red' => $d['red_8bit'],
			'green' => $d['green_8bit'],
			'blue' => $d['blue_8bit'],
		]);

		$colorReal = null;
		$redReal = $d['red_real'];
		$greenReal = $d['green_real'];
		$blueReal = $d['blue_real'];
		if (!is_null($redReal) && !is_null($greenReal) && !is_null($blueReal)) {
			$colorReal = new ColorReal();
			$colorReal->setData([
				'red' => floatval($redReal),
				'green' => floatval($greenReal),
				'blue' => floatval($blueReal),
			]);
		}

		$result = new Color();
		$result->setData([
			'colors_id' => Uuid::fromBytes($d['colors_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'color_8bit' => $color8bit,
			'color_real' => $colorReal,
		]);
		return $result;
	}

	/**
	 * Resolve the projects_id for a given colors_id, then delegate
	 * privilege check to ProjectsPrivilegesRepo.
	 *
	 * @return RetValueOrError<\dev_t0r\trvis_backend\model\InviteKeyPrivilegeType>
	 */
	public function selectPrivilegeType(
		UuidInterface $id,
		string $userId = Constants::UID_ANONYMOUS,
		bool $includeAnonymous = false,
		bool $selectForUpdate = false,
	): RetValueOrError {
		$this->logger->debug(
			"selectPrivilegeType(colorsId:{id}, userId:{userId})",
			['id' => $id, 'userId' => $userId],
		);
		try {
			$query = $this->db->prepare(
				'SELECT projects_id FROM colors'
				. ' WHERE colors_id = :colors_id AND deleted_at IS NULL'
				. ($selectForUpdate ? ' FOR UPDATE' : '')
				. ';'
			);
			$query->bindValue(':colors_id', $id->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				return Utils::errColorNotFound();
			}
			$row = $query->fetch(PDO::FETCH_ASSOC);
			$projectsId = Uuid::fromBytes($row['projects_id']);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to resolve projects_id for color ({errorCode})",
				["errorCode" => $errCode],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
				$errCode,
			);
		}

		return $this->projectsPrivilegesRepo->selectPrivilegeType(
			id: $projectsId,
			userId: $userId,
			includeAnonymous: $includeAnonymous,
			selectForUpdate: $selectForUpdate,
		);
	}

	/**
	 * @return RetValueOrError<Color>
	 */
	public function selectOne(
		UuidInterface $colorsId,
	): RetValueOrError {
		$this->logger->debug(
			"selectOne(colorsId:{colorsId})",
			['colorsId' => $colorsId],
		);
		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					colors_id,
					projects_id,
					description,
					created_at,
					name,
					red_8bit,
					green_8bit,
					blue_8bit,
					red_real,
					green_real,
					blue_real
				FROM
					colors
				WHERE
					colors_id = :colors_id
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':colors_id', $colorsId->getBytes(), PDO::PARAM_STR);
			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}

			$data = $query->fetch(PDO::FETCH_ASSOC);
			if (!$data) {
				$this->logger->info(
					"Color not found ({colorsId})",
					['colorsId' => $colorsId],
				);
				return Utils::errColorNotFound();
			}

			return RetValueOrError::withValue(self::fetchResultToColor($data));
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $e->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	private static function getSelectPageQuery(bool $hasTopId, bool $countOnly): string
	{
		$WHERE_TOP_ID = $hasTopId ? ' colors.colors_id <= :top_id AND ' : ' ';
		$COLUMNS = $countOnly ? ' COUNT(*) AS count ' : <<<SQL
			colors.colors_id,
			colors.projects_id,
			colors.description,
			colors.created_at,
			colors.name,
			colors.red_8bit,
			colors.green_8bit,
			colors.blue_8bit,
			colors.red_real,
			colors.green_real,
			colors.blue_real

			SQL;
		$PAGING_QUERY = $countOnly ? '' : <<<SQL

			ORDER BY
				colors.colors_id DESC
			LIMIT
				:perPage
			OFFSET
				:offset
			SQL;
		return <<<SQL
			SELECT
				$COLUMNS
			FROM
				colors
			WHERE
				colors.projects_id = :projects_id
			AND
				$WHERE_TOP_ID
				colors.deleted_at IS NULL
			$PAGING_QUERY
			SQL;
	}

	/**
	 * @return RetValueOrError<array<Color>>
	 */
	public function selectPage(
		UuidInterface $projectsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"selectPage(projectsId:{projectsId}, page:{page})",
			['projectsId' => $projectsId, 'page' => $pageFrom1],
		);
		$hasTopId = !is_null($topId);
		try {
			$query = $this->db->prepare(self::getSelectPageQuery($hasTopId, false));
			$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
			if ($hasTopId) {
				$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
			}
			$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
			$query->bindValue(':offset', ($pageFrom1 - 1) * $perPage, PDO::PARAM_INT);

			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}

			$colors = array_map(
				fn ($data) => self::fetchResultToColor($data),
				$query->fetchAll(PDO::FETCH_ASSOC),
			);
			return RetValueOrError::withValue($colors);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $e->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * @return RetValueOrError<int>
	 */
	public function selectPageTotalCount(
		UuidInterface $projectsId,
		?UuidInterface $topId,
	): RetValueOrError {
		$hasTopId = !is_null($topId);
		try {
			$query = $this->db->prepare(self::getSelectPageQuery($hasTopId, true));
			$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
			if ($hasTopId) {
				$query->bindValue(':top_id', $topId->getBytes(), PDO::PARAM_STR);
			}

			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}

			$row = $query->fetch(PDO::FETCH_ASSOC);
			$totalCount = $row ? (int)$row['count'] : 0;
			return RetValueOrError::withValue($totalCount);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $e->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<Color>>
	 */
	public function selectList(
		array $idList,
	): RetValueOrError {
		if (empty($idList)) {
			return RetValueOrError::withValue([]);
		}
		$placeholders = implode(',', array_map(fn ($i) => ":id_$i", array_keys($idList)));
		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					colors_id,
					projects_id,
					description,
					created_at,
					name,
					red_8bit,
					green_8bit,
					blue_8bit,
					red_real,
					green_real,
					blue_real
				FROM
					colors
				WHERE
					colors_id IN ($placeholders)
				AND
					deleted_at IS NULL
				ORDER BY
					colors_id DESC
				;
				SQL
			);
			foreach ($idList as $i => $id) {
				$query->bindValue(":id_$i", $id->getBytes(), PDO::PARAM_STR);
			}
			$isSuccess = $query->execute();
			if (!$isSuccess) {
				$errCode = $query->errorCode();
				$this->logger->error(
					"Failed to execute SQL ({errorCode} -> {errorInfo})",
					[
						"errorCode" => $errCode,
						"errorInfo" => implode('\n\t', $query->errorInfo()),
					],
				);
				return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
			}
			$colors = array_map(
				fn ($data) => self::fetchResultToColor($data),
				$query->fetchAll(PDO::FETCH_ASSOC),
			);
			return RetValueOrError::withValue($colors);
		} catch (\PDOException $e) {
			$errCode = $e->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $e->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * @param array<Color> $dataList
	 * @return RetValueOrError<null>
	 */
	public function insertList(
		UuidInterface $projectsId,
		string $owner,
		array $insertIdList,
		array $dataList,
		PDO $db,
	): RetValueOrError {
		$count = count($dataList);
		if ($count === 0) {
			return RetValueOrError::withValue(null);
		}

		$indices = range(0, $count - 1);
		$valuesPlaceholders = implode(
			', ',
			array_map(
				fn ($i) => <<<SQL
					(
						:colors_id_{$i},
						:projects_id,
						:description_{$i},
						:owner,
						:name_{$i},
						:red_8bit_{$i},
						:green_8bit_{$i},
						:blue_8bit_{$i},
						:red_real_{$i},
						:green_real_{$i},
						:blue_real_{$i}
					)
					SQL,
				$indices,
			)
		);

		try {
			$query = $db->prepare(<<<SQL
				INSERT INTO colors (
					colors_id,
					projects_id,
					description,
					owner,
					name,
					red_8bit,
					green_8bit,
					blue_8bit,
					red_real,
					green_real,
					blue_real
				) VALUES $valuesPlaceholders
				;
				SQL
			);
			$query->bindValue(':projects_id', $projectsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $owner, PDO::PARAM_STR);

			foreach ($dataList as $i => $d) {
				$query->bindValue(":colors_id_$i", $insertIdList[$i]->getBytes(), PDO::PARAM_STR);
				$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
				$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
				$query->bindValue(":red_8bit_$i", $d->color_8bit->red, PDO::PARAM_INT);
				$query->bindValue(":green_8bit_$i", $d->color_8bit->green, PDO::PARAM_INT);
				$query->bindValue(":blue_8bit_$i", $d->color_8bit->blue, PDO::PARAM_INT);
				$query->bindValue(":red_real_$i", $d->color_real?->red, PDO::PARAM_STR);
				$query->bindValue(":green_real_$i", $d->color_real?->green, PDO::PARAM_STR);
				$query->bindValue(":blue_real_$i", $d->color_real?->blue, PDO::PARAM_STR);
			}

			$isSuccess = $query->execute();
			if ($isSuccess) {
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			["errorCode" => $errCode, "errorInfo" => $errorInfo],
		);
		return Utils::mapPdoIntegrityError($errCode, $driverCode, "Color");
	}

	/**
	 * @param array<string, mixed> $kvpArray  Already-expanded flat column map
	 *   (color_8bit/color_real already decomposed to red_8bit etc. by service)
	 * @return RetValueOrError<null>
	 */
	public function update(
		UuidInterface $colorsId,
		array $kvpArray,
	): RetValueOrError {
		if (empty($kvpArray)) {
			return RetValueOrError::withValue(null);
		}

		$this->logger->info(
			"update({colorsId})",
			['colorsId' => $colorsId],
		);

		$ALLOWED_COLUMNS = [
			'name',
			'description',
			'red_8bit',
			'green_8bit',
			'blue_8bit',
			'red_real',
			'green_real',
			'blue_real',
		];
		$setParts = [];
		foreach ($ALLOWED_COLUMNS as $col) {
			if (array_key_exists($col, $kvpArray)) {
				$setParts[] = "$col = :$col";
			}
		}
		if (empty($setParts)) {
			return RetValueOrError::withValue(null);
		}

		$setClause = implode(', ', $setParts);
		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE colors SET
					$setClause,
					updated_at = CURRENT_TIMESTAMP()
				WHERE
					colors_id = :colors_id
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':colors_id', $colorsId->getBytes(), PDO::PARAM_STR);
			foreach ($ALLOWED_COLUMNS as $col) {
				if (array_key_exists($col, $kvpArray)) {
					if ($col === 'red_8bit' || $col === 'green_8bit' || $col === 'blue_8bit') {
						$query->bindValue(":$col", $kvpArray[$col], PDO::PARAM_INT);
					} else {
						$query->bindValue(":$col", $kvpArray[$col], PDO::PARAM_STR);
					}
				}
			}

			$isSuccess = $query->execute();
			if ($isSuccess) {
				if ($query->rowCount() === 0) {
					$this->logger->info(
						"Color not found ({colorsId})",
						['colorsId' => $colorsId],
					);
					return Utils::errColorNotFound();
				}
				return RetValueOrError::withValue(null);
			}

			$errCode = $query->errorCode();
			$errorInfoArr = $query->errorInfo();
			$driverCode = isset($errorInfoArr[1]) ? (int)$errorInfoArr[1] : null;
			$errorInfo = implode('\n\t', $errorInfoArr);
		} catch (\PDOException $ex) {
			$errCode = strval($ex->getCode());
			$driverCode = isset($ex->errorInfo[1]) ? (int)$ex->errorInfo[1] : null;
			$errorInfo = $ex->getMessage();
		}

		$this->logger->error(
			"Failed to execute SQL ({errorCode} -> {errorInfo})",
			["errorCode" => $errCode, "errorInfo" => $errorInfo],
		);
		return RetValueOrError::withError(
			Constants::HTTP_INTERNAL_SERVER_ERROR,
			"Failed to execute SQL - " . $errCode,
		);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function deleteOne(
		UuidInterface $colorsId,
	): RetValueOrError {
		$this->logger->info(
			"deleteOne({colorsId})",
			['colorsId' => $colorsId],
		);
		try {
			$query = $this->db->prepare(<<<SQL
				UPDATE
					colors
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					colors_id = :colors_id
				AND
					deleted_at IS NULL
				;
				SQL
			);
			$query->bindValue(':colors_id', $colorsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			if ($query->rowCount() === 0) {
				$this->logger->info(
					"Color not found ({colorsId})",
					['colorsId' => $colorsId],
				);
				return Utils::errColorNotFound();
			}
			return RetValueOrError::withValue(null);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $ex->getMessage()],
			);
			return RetValueOrError::withError(500, "Failed to execute SQL - " . $errCode);
		}
	}

	/**
	 * Return the IDs in $idList that do NOT exist in colors (or are deleted),
	 * optionally scoped to the project of a particular work_groups_id ancestor.
	 *
	 * Called by W3 TimetableRowsService with the timetable row's workGroupsId.
	 * `colors` is now Project-rooted, so the scope check verifies the color
	 * belongs to the SAME PROJECT as the given work group
	 * (colors.projects_id = the work group's projects_id).
	 *
	 * @param array<UuidInterface> $idList
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function nonExistIdCheck(
		array $idList,
		?UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			'nonExistIdCheck colors idList: {idList}, workGroupsId: {workGroupsId}',
			[
				'idList' => $idList,
				'workGroupsId' => $workGroupsId,
			],
		);

		$idCount = count($idList);
		if ($idCount === 0) {
			return RetValueOrError::withValue([]);
		} elseif ($idCount === 1) {
			$idPlaceholders = 'SELECT UNHEX(:id_0) AS id';
		} else {
			$idPlaceholders = implode(' UNION ', array_map(
				fn ($i) => "SELECT UNHEX(:id_$i)" . ($i === 0 ? ' AS id' : ''),
				range(0, $idCount - 1),
			));
		}

		if (is_null($workGroupsId)) {
			$PROJECT_SCOPE_CHECK = '';
		} else {
			$PROJECT_SCOPE_CHECK =
				' AND colors.projects_id = (SELECT projects_id FROM work_groups WHERE work_groups_id = :work_groups_id)';
		}

		try {
			$query = $this->db->prepare(<<<SQL
				SELECT
					id
				FROM
					(
						{$idPlaceholders}
					) AS id_list
				WHERE NOT EXISTS(
					SELECT
						*
					FROM
						colors

					WHERE
						colors_id = id_list.id
					AND
						colors.deleted_at IS NULL

					{$PROJECT_SCOPE_CHECK}
				)
				SQL
			);

			for ($i = 0; $i < $idCount; $i++) {
				$query->bindValue(":id_$i", $idList[$i]->getHex()->toString(), PDO::PARAM_STR);
			}
			if (!is_null($workGroupsId)) {
				$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$result = $query->fetchAll(PDO::FETCH_ASSOC);

			$nonExistIdList = array_map(
				fn ($data) => Uuid::fromBytes($data['id']),
				$result,
			);

			$this->logger->debug(
				'nonExistIdList(colors): {nonExistIdList}',
				['nonExistIdList' => $nonExistIdList],
			);

			return RetValueOrError::withValue($nonExistIdList);
		} catch (\PDOException $ex) {
			$errCode = $ex->getCode();
			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				["errorCode" => $errCode, "errorInfo" => $ex->getMessage()],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}
}
