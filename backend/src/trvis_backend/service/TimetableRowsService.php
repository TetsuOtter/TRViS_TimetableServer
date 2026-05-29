<?php

declare(strict_types=1);

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\InviteKeyPrivilegeType;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\repo\ColorsRepo;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
use dev_t0r\trvis_backend\repo\TimetableRowsRepo;
use dev_t0r\trvis_backend\repo\TrainsRepo;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * TimetableRow service. Train-rooted (trains_id FK).
 *
 * Hybrid of two locked patterns (see CONTRIBUTING-P3.md §11):
 *
 *  - Privilege shape = TrainsService shape. create/getPage resolve
 *    privilege through the PARENT (TrainsRepo) — read/write-fail →
 *    errTrainNotFound. getOne/update/delete resolve through the TARGET
 *    (TimetableRowsRepo) — getOne read-fail → errContentNotFound;
 *    update/delete read/write-fail → errTimetableRowNotFound. On
 *    `$privResult->isError` the raw error is returned (for the target
 *    repo that is already errTimetableRowNotFound; never 403, never
 *    ::admin — read == write everywhere, faithful to legacy
 *    MyServiceBase::checkPrivilegeToWrite which returns the read-fail
 *    NotFound on BOTH read- and write-fail).
 *
 *  - update() uses the ColorApi/ColorsService kvpArray flow: the Api
 *    builds $kvpArray via Utils::getArrayForUpdateSource and passes it in;
 *    the legacy MyServiceBase::beforeInsert / beforeUpdate hooks are
 *    inlined here (FK existence checks for stations / station_tracks /
 *    colors scoped to the resolved work_groups_id).
 *
 * The legacy TimetableRowsService instantiated parentRepo: new TrainsRepo
 * and used StationsRepo / StationTracksRepo / ColorsRepo for the
 * nonExistIdCheck FK guards — mirrored 1:1 below without the base class.
 */
final class TimetableRowsService
{
	private readonly TimetableRowsRepo $timetableRowsRepo;
	private readonly TrainsRepo $trainsRepo;
	private readonly StationsRepo $stationsRepo;
	private readonly StationTracksRepo $stationTracksRepo;
	private readonly ColorsRepo $colorsRepo;

	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
		$this->timetableRowsRepo = new TimetableRowsRepo($db, $logger);
		$this->trainsRepo = new TrainsRepo($db, $logger);
		$this->stationsRepo = new StationsRepo($db, $logger);
		$this->stationTracksRepo = new StationTracksRepo($db, $logger);
		$this->colorsRepo = new ColorsRepo($db, $logger);
	}

	/**
	 * @return RetValueOrError<TimetableRow>
	 */
	public function getOne(
		string $userId,
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			"getOne(userId:{userId}, timetableRowsId:{timetableRowsId})",
			[
				'userId' => $userId,
				'timetableRowsId' => $timetableRowsId,
			],
		);

		$privResult = $this->timetableRowsRepo->selectPrivilegeType(
			id: $timetableRowsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		return $this->timetableRowsRepo->selectTimetableRowOne($timetableRowsId);
	}

	/**
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function getPage(
		string $userId,
		UuidInterface $trainsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			"getPage(userId:{userId}, trainsId:{trainsId}, page:{page}, perPage:{perPage})",
			[
				'userId' => $userId,
				'trainsId' => $trainsId,
				'page' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$privResult = $this->trainsRepo->selectPrivilegeType(
			id: $trainsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errContentNotFound();
		}

		// M10: two independent SELECTs — accepted (count, page) eventual-consistency; see RetValueOrError::withTotalCount().
		$totalCountResult = $this->timetableRowsRepo->selectTimetableRowPageTotalCount(
			trainsId: $trainsId,
			topId: $topId,
		);
		$selectResult = $this->timetableRowsRepo->selectTimetableRowPage(
			trainsId: $trainsId,
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
	 * @return RetValueOrError<array<TimetableRow>>
	 */
	public function create(
		UuidInterface $trainsId,
		string $userId,
		array $dataList,
	): RetValueOrError {
		$this->logger->debug(
			"create(trainsId:{trainsId}, userId:{userId})",
			[
				'trainsId' => $trainsId,
				'userId' => $userId,
			],
		);

		if (Constants::BULK_INSERT_MAX_COUNT < count($dataList)) {
			return RetValueOrError::withError(
				statusCode: Constants::HTTP_BAD_REQUEST,
				errorMsg: "Too many data to insert at once. Max count is " . Constants::BULK_INSERT_MAX_COUNT,
			);
		}

		$privResult = $this->trainsRepo->selectPrivilegeType(
			id: $trainsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errTrainNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$insertedIds = [];
		$this->db->beginTransaction();
		try {
			$beforeInsertResult = $this->beforeInsert(
				trainsId: $trainsId,
				valueList: $dataList,
			);
			if (!is_null($beforeInsertResult)) {
				if ($beforeInsertResult->isError) {
					$this->db->rollBack();
				} else {
					$this->db->commit();
				}
				return $beforeInsertResult;
			}

			foreach ($dataList as $d) {
				$timetableRowsId = Uuid::uuid7();
				$insertResult = $this->timetableRowsRepo->insertTimetableRow(
					timetableRowsId: $timetableRowsId,
					trainsId: $trainsId,
					owner: $userId,
					stationsId: $d->stations_id,
					stationTracksId: $d->station_tracks_id,
					colorsIdMarker: $d->colors_id_marker,
					description: $d->description,
					driveTimeMm: $d->drive_time_mm,
					driveTimeSs: $d->drive_time_ss,
					isOperationOnlyStop: $d->is_operation_only_stop ?? false,
					isPass: $d->is_pass ?? false,
					hasBracket: $d->has_bracket ?? false,
					isLastStop: $d->is_last_stop ?? false,
					arriveTimeHh: $d->arrive_time_hh,
					arriveTimeMm: $d->arrive_time_mm,
					arriveTimeSs: $d->arrive_time_ss,
					departureTimeHh: $d->departure_time_hh,
					departureTimeMm: $d->departure_time_mm,
					departureTimeSs: $d->departure_time_ss,
					runInLimit: $d->run_in_limit,
					runOutLimit: $d->run_out_limit,
					remarks: $d->remarks,
					arriveStr: $d->arrive_str,
					departureStr: $d->departure_str,
					markerText: $d->marker_text,
					workType: $d->work_type instanceof WorkAtStationType
						? $d->work_type
						: WorkAtStationType::fromOrNull($d->work_type),
				);
				if ($insertResult->isError) {
					$this->db->rollBack();
					return $insertResult;
				}
				$insertedIds[] = $timetableRowsId;
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to create timetable_row: {exception}",
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

		$selectListResult = $this->timetableRowsRepo->selectListByIds($insertedIds);
		if ($selectListResult->isError) {
			return $selectListResult;
		}
		$byId = [];
		foreach ($selectListResult->value as $m) {
			$byId[(string)$m->timetable_rows_id] = $m;
		}
		if (count($byId) !== count($insertedIds)) {
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'inconsistent read-back after bulk insert',
			);
		}
		$results = [];
		foreach ($insertedIds as $id) {
			$results[] = $byId[(string)$id];
		}

		return RetValueOrError::withValue($results, Constants::HTTP_CREATED);
	}

	/**
	 * @return RetValueOrError<TimetableRow>
	 */
	public function update(
		string $userId,
		UuidInterface $timetableRowsId,
		object $data,
		array $kvpArray,
	): RetValueOrError {
		$this->logger->debug(
			"update(userId:{userId}, timetableRowsId:{timetableRowsId})",
			[
				'userId' => $userId,
				'timetableRowsId' => $timetableRowsId,
			],
		);

		$privResult = $this->timetableRowsRepo->selectPrivilegeType(
			id: $timetableRowsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errTimetableRowNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		$this->db->beginTransaction();
		try {
			$beforeUpdateResult = $this->beforeUpdate(
				timetableRowsId: $timetableRowsId,
				kvpArray: $kvpArray,
			);
			if (!is_null($beforeUpdateResult)) {
				if ($beforeUpdateResult->isError) {
					$this->db->rollBack();
				} else {
					$this->db->commit();
				}
				return $beforeUpdateResult;
			}

			$updateResult = $this->timetableRowsRepo->update($timetableRowsId, $kvpArray);
			if ($updateResult->isError) {
				$this->db->rollBack();
				return $updateResult;
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->logger->error(
				"Failed to update timetable_row: {exception}",
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

		return $this->timetableRowsRepo->selectTimetableRowOne($timetableRowsId);
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function delete(
		string $userId,
		UuidInterface $timetableRowsId,
	): RetValueOrError {
		$this->logger->debug(
			"delete(userId:{userId}, timetableRowsId:{timetableRowsId})",
			[
				'userId' => $userId,
				'timetableRowsId' => $timetableRowsId,
			],
		);

		$privResult = $this->timetableRowsRepo->selectPrivilegeType(
			id: $timetableRowsId,
			userId: $userId,
			includeAnonymous: true,
		);
		if ($privResult->isError) {
			return $privResult;
		}
		$priv = $privResult->value;
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::read)) {
			return Utils::errTimetableRowNotFound();
		}
		if (!$priv->hasPrivilege(InviteKeyPrivilegeType::write)) {
			return Utils::errForbidden();
		}

		return $this->timetableRowsRepo->deleteTimetableRow($timetableRowsId);
	}

	/**
	 * Inlined legacy MyServiceBase::beforeInsert hook — resolves the
	 * work_groups_id via the parent (TrainsRepo) then verifies every
	 * referenced station / station_track / color exists within that scope.
	 *
	 * @param array<TimetableRow> $valueList
	 * @return ?RetValueOrError<mixed>
	 */
	private function beforeInsert(
		UuidInterface $trainsId,
		array $valueList,
	): ?RetValueOrError {
		$workGroupsIdCheckResult = $this->trainsRepo->selectWorkGroupsId($trainsId);
		if ($workGroupsIdCheckResult->isError) {
			$this->logger->warning('Failed to select work_groups_id', [
				'trainsId' => $trainsId,
				'error' => $workGroupsIdCheckResult->errorMsg,
			]);
			return $workGroupsIdCheckResult;
		}
		$workGroupsId = $workGroupsIdCheckResult->value;

		$nonExistStationIdCheckResult = $this->stationsRepo->nonExistIdCheck(
			idList: array_map(fn (TimetableRow $row) => $row->stations_id, $valueList),
			workGroupsId: $workGroupsId,
		);
		if ($nonExistStationIdCheckResult->isError) {
			return $nonExistStationIdCheckResult;
		}
		if (0 < count($nonExistStationIdCheckResult->value)) {
			$nonExistStationIdListStr = implode(', ', $nonExistStationIdCheckResult->value);
			return RetValueOrError::withBadReq(
				errorMsg: "some station id are not exist - [{$nonExistStationIdListStr}]",
			);
		}

		$stationTracksIdList = array_map(
			fn (TimetableRow $row) => $row->station_tracks_id,
			array_filter(
				$valueList,
				fn (TimetableRow $row) => !is_null($row->station_tracks_id),
			),
		);
		if (0 < count($stationTracksIdList)) {
			$nonExistStationTrackIdCheckResult = $this->stationTracksRepo->nonExistIdCheck(
				idList: $stationTracksIdList,
				workGroupsId: $workGroupsId,
			);
			if ($nonExistStationTrackIdCheckResult->isError) {
				return $nonExistStationTrackIdCheckResult;
			}
			if (0 < count($nonExistStationTrackIdCheckResult->value)) {
				$nonExistStationTrackIdListStr = implode(', ', $nonExistStationTrackIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some station track id are not exist - [{$nonExistStationTrackIdListStr}]",
				);
			}
		}

		$colorsIdList = array_map(
			fn (TimetableRow $row) => $row->colors_id_marker,
			array_filter(
				$valueList,
				fn (TimetableRow $row) => !is_null($row->colors_id_marker),
			),
		);
		if (0 < count($colorsIdList)) {
			$nonExistColorIdCheckResult = $this->colorsRepo->nonExistIdCheck(
				idList: $colorsIdList,
				workGroupsId: $workGroupsId,
			);
			if ($nonExistColorIdCheckResult->isError) {
				return $nonExistColorIdCheckResult;
			}
			if (0 < count($nonExistColorIdCheckResult->value)) {
				$nonExistColorIdListStr = implode(', ', $nonExistColorIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some color id are not exist - [{$nonExistColorIdListStr}]",
				);
			}
		}

		return null;
	}

	/**
	 * Inlined legacy MyServiceBase::beforeUpdate hook — when the update
	 * touches stations_id / station_tracks_id / colors_id_marker, resolve
	 * the work_groups_id via the TARGET (TimetableRowsRepo) and verify the
	 * referenced ids exist within that scope. station_tracks_id /
	 * colors_id_marker are skipped when present-but-null (faithful to
	 * legacy: a null clears the FK and needs no existence check).
	 *
	 * @param array<string, mixed> $kvpArray
	 * @return ?RetValueOrError<mixed>
	 */
	private function beforeUpdate(
		UuidInterface $timetableRowsId,
		array &$kvpArray,
	): ?RetValueOrError {
		$hasStationsId = array_key_exists('stations_id', $kvpArray);
		$hasStationTracksId = array_key_exists('station_tracks_id', $kvpArray)
			&& !is_null($kvpArray['station_tracks_id']);
		$hasColorsIdMarker = array_key_exists('colors_id_marker', $kvpArray);
		if (!$hasStationsId && !$hasStationTracksId && !$hasColorsIdMarker) {
			return null;
		}

		$workGroupsIdCheckResult = $this->timetableRowsRepo->selectWorkGroupsId($timetableRowsId);
		if ($workGroupsIdCheckResult->isError) {
			$this->logger->warning('Failed to select work_groups_id', [
				'timetableRowsId' => $timetableRowsId,
				'error' => $workGroupsIdCheckResult->errorMsg,
			]);
			return $workGroupsIdCheckResult;
		}
		$workGroupsId = $workGroupsIdCheckResult->value;

		if (array_key_exists('stations_id', $kvpArray)) {
			$nonExistStationIdCheckResult = $this->stationsRepo->nonExistIdCheck(
				idList: [$kvpArray['stations_id']],
				workGroupsId: $workGroupsId,
			);
			if ($nonExistStationIdCheckResult->isError) {
				return $nonExistStationIdCheckResult;
			}
			if (0 < count($nonExistStationIdCheckResult->value)) {
				$nonExistStationIdListStr = implode(', ', $nonExistStationIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some station id are not exist - [{$nonExistStationIdListStr}]",
				);
			}
		}

		if (array_key_exists('station_tracks_id', $kvpArray) && !is_null($kvpArray['station_tracks_id'])) {
			$nonExistStationTrackIdCheckResult = $this->stationTracksRepo->nonExistIdCheck(
				idList: [$kvpArray['station_tracks_id']],
				workGroupsId: $workGroupsId,
			);
			if ($nonExistStationTrackIdCheckResult->isError) {
				return $nonExistStationTrackIdCheckResult;
			}
			if (0 < count($nonExistStationTrackIdCheckResult->value)) {
				$nonExistStationTrackIdListStr = implode(', ', $nonExistStationTrackIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some station track id are not exist - [{$nonExistStationTrackIdListStr}]",
				);
			}
		}

		if (array_key_exists('colors_id_marker', $kvpArray) && !is_null($kvpArray['colors_id_marker'])) {
			$nonExistColorIdCheckResult = $this->colorsRepo->nonExistIdCheck(
				idList: [$kvpArray['colors_id_marker']],
				workGroupsId: $workGroupsId,
			);
			if ($nonExistColorIdCheckResult->isError) {
				return $nonExistColorIdCheckResult;
			}
			if (0 < count($nonExistColorIdCheckResult->value)) {
				$nonExistColorIdListStr = implode(', ', $nonExistColorIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some color id are not exist - [{$nonExistColorIdListStr}]",
				);
			}
		}

		return null;
	}
}
