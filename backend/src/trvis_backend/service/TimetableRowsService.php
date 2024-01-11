<?php

namespace dev_t0r\trvis_backend\service;

use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\repo\StationsRepo;
use dev_t0r\trvis_backend\repo\StationTracksRepo;
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
	private readonly StationsRepo $stationsRepo;
	private readonly StationTracksRepo $stationTracksRepo;

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

		$this->stationsRepo = new StationsRepo($db, $logger);
		$this->stationTracksRepo = new StationTracksRepo($db, $logger);
	}

	protected function beforeInsert(
		UuidInterface $parentId,
		string $ownerUserId,
		/** @param array<UuidInterface> $idList */
		array $idList,
		/** @param array<TimetableRow> $valueList */
		array $valueList,
	): ?RetValueOrError {
		$workGroupsIdCheckResult = $this->parentRepo->selectWorkGroupsId($parentId);
		if ($workGroupsIdCheckResult->isError) {
			$this->logger->warning('Failed to select work_groups_id', [
				'parentId' => $parentId,
				'ownerUserId' => $ownerUserId,
				'idList' => $idList,
				'valueList' => $valueList,
				'error' => $workGroupsIdCheckResult->errorMsg,
			]);
			return $workGroupsIdCheckResult;
		}

		$workGroupsId = $workGroupsIdCheckResult->value;
		$this->logger->debug('work_groups_id={workGroupsId}', [
			'workGroupsId' => $workGroupsId,
		]);

		$nonExistStationIdCheckResult = $this->stationsRepo->nonExistIdCheck(
			idList: array_map(fn(TimetableRow $row) => $row->stations_id, $valueList),
			workGroupsId: $workGroupsId,
		);
		if ($nonExistStationIdCheckResult->isError) {
			$this->logger->warning('Failed to check non exist station id - {error}', [
				'error' => $nonExistStationIdCheckResult->errorMsg,
			]);
			return $nonExistStationIdCheckResult;
		}
		if (0 < count($nonExistStationIdCheckResult->value)) {
			$this->logger->warning('some station id are not exist - {nonExistStationIdList}', [
				'nonExistStationIdList' => $nonExistStationIdCheckResult->value,
			]);
			$nonExistStationIdListStr = implode(', ', $nonExistStationIdCheckResult->value);
			return RetValueOrError::withBadReq(
				errorMsg: "some station id are not exist - [{$nonExistStationIdListStr}]",
			);
		}

		$stationTracksIdList = array_map(
			fn(TimetableRow $row) => $row->station_tracks_id,
			array_filter(
				$valueList,
				fn(TimetableRow $row) => !is_null($row->station_tracks_id)
			)
		);
		if (0 < count($stationTracksIdList)) {
			$nonExistStationTrackIdCheckResult = $this->stationTracksRepo->nonExistIdCheck(
				idList: $stationTracksIdList,
				workGroupsId: $workGroupsId,
			);
			if ($nonExistStationTrackIdCheckResult->isError) {
				$this->logger->warning('Failed to check non exist station track id - {error}', [
					'error' => $nonExistStationTrackIdCheckResult->errorMsg,
				]);
				return $nonExistStationTrackIdCheckResult;
			}
			if (0 < count($nonExistStationTrackIdCheckResult->value)) {
				$this->logger->warning('some station track id are not exist - {nonExistStationTrackIdList}', [
					'nonExistStationTrackIdList' => $nonExistStationTrackIdCheckResult->value,
				]);
				$nonExistStationTrackIdListStr = implode(', ', $nonExistStationTrackIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some station track id are not exist - [{$nonExistStationTrackIdListStr}]",
				);
			}
		}

		// TODO: Colorの存在チェック

		return null;
	}

	protected function beforeUpdate(
		string $senderUserId,
		UuidInterface $id,
		/** @param T $data */
		object $data,
		/** @param array<string, mixed> $kvpArray */
		array $kvpArray,
	): ?RetValueOrError {
		$hasStationsId = array_key_exists('stations_id', $kvpArray);
		$hasStationTracksId = array_key_exists('station_tracks_id', $kvpArray) && !is_null($kvpArray['station_tracks_id']);
		$hasColorsIdMarker = array_key_exists('colors_id_marker', $kvpArray);
		$this->logger->debug('beforeUpdate hasStationsId={hasStationsId}, hasStationTracksId={hasStationTracksId}, hasColorsIdMarker={hasColorsIdMarker}', [
			'hasStationsId' => $hasStationsId,
			'hasStationTracksId' => $hasStationTracksId,
			'hasColorsIdMarker' => $hasColorsIdMarker,
		]);
		if (!$hasStationsId && !$hasStationTracksId && !$hasColorsIdMarker) {
			return null;
		}

		$workGroupsIdCheckResult = $this->targetRepo->selectWorkGroupsId($id);
		if ($workGroupsIdCheckResult->isError) {
			$this->logger->warning('Failed to select work_groups_id', [
				'id' => $id,
				'props' => $kvpArray,
				'error' => $workGroupsIdCheckResult->errorMsg,
			]);
			return $workGroupsIdCheckResult;
		}

		$workGroupsId = $workGroupsIdCheckResult->value;
		$this->logger->debug('work_groups_id={workGroupsId}', [
			'workGroupsId' => $workGroupsId,
		]);

		if (array_key_exists('stations_id', $kvpArray)) {
			$nonExistStationIdCheckResult = $this->stationsRepo->nonExistIdCheck(
				idList: [$kvpArray['stations_id']],
				workGroupsId: $workGroupsId,
			);
			if ($nonExistStationIdCheckResult->isError) {
				$this->logger->warning('Failed to check non exist station id - {error}', [
					'error' => $nonExistStationIdCheckResult->errorMsg,
				]);
				return $nonExistStationIdCheckResult;
			}
			if (0 < count($nonExistStationIdCheckResult->value)) {
				$this->logger->warning('some station id are not exist - {nonExistStationIdList}', [
					'nonExistStationIdList' => $nonExistStationIdCheckResult->value,
				]);
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
				$this->logger->warning('Failed to check non exist station track id - {error}', [
					'error' => $nonExistStationTrackIdCheckResult->errorMsg,
				]);
				return $nonExistStationTrackIdCheckResult;
			}
			if (0 < count($nonExistStationTrackIdCheckResult->value)) {
				$this->logger->warning('some station track id are not exist - {nonExistStationTrackIdList}', [
					'nonExistStationTrackIdList' => $nonExistStationTrackIdCheckResult->value,
				]);
				$nonExistStationTrackIdListStr = implode(', ', $nonExistStationTrackIdCheckResult->value);
				return RetValueOrError::withBadReq(
					errorMsg: "some station track id are not exist - [{$nonExistStationTrackIdListStr}]",
				);
			}
		}

		// TODO: Colorの存在チェック

		return null;
	}
}
