<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\TimetableRow;
use dev_t0r\trvis_backend\model\TRViSJsonTimetableRow;
use dev_t0r\trvis_backend\model\WorkAtStationType;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<TimetableRow>
 */
final class TimetableRowsRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'timetable_rows',
			parentTableNameList: ['trains', 'works', 'work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		timetable_rows.timetable_rows_id AS timetable_rows_id,
		timetable_rows.trains_id AS trains_id,
		timetable_rows.stations_id AS stations_id,
		timetable_rows.station_tracks_id AS station_tracks_id,
		timetable_rows.colors_id AS colors_id_marker,

		timetable_rows.description AS description,
		timetable_rows.created_at AS created_at,

		timetable_rows.drive_time_mm AS drive_time_mm,
		timetable_rows.drive_time_ss AS drive_time_ss,

		timetable_rows.is_operation_only_stop AS is_operation_only_stop,
		timetable_rows.is_pass AS is_pass,
		timetable_rows.has_bracket AS has_bracket,
		timetable_rows.is_last_stop AS is_last_stop,

		timetable_rows.arrive_time_hh AS arrive_time_hh,
		timetable_rows.arrive_time_mm AS arrive_time_mm,
		timetable_rows.arrive_time_ss AS arrive_time_ss,

		timetable_rows.departure_time_hh AS departure_time_hh,
		timetable_rows.departure_time_mm AS departure_time_mm,
		timetable_rows.departure_time_ss AS departure_time_ss,

		timetable_rows.run_in_limit AS run_in_limit,
		timetable_rows.run_out_limit AS run_out_limit,

		timetable_rows.remarks AS remarks,

		timetable_rows.arrive_str AS arrive_str,
		timetable_rows.departure_str AS departure_str,

		timetable_rows.marker_text AS marker_text,

		timetable_rows.work_type AS work_type

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new TimetableRow();
		$stationTracksId = $d['station_tracks_id'];
		$colorsIdMarker = $d['colors_id_marker'];
		$result->setData([
			'timetable_rows_id' => Uuid::fromBytes($d['timetable_rows_id']),
			'trains_id' => Uuid::fromBytes($d['trains_id']),
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'station_tracks_id' => is_null($stationTracksId) ? null : Uuid::fromBytes($stationTracksId),
			'colors_id_marker' => is_null($colorsIdMarker) ? null : Uuid::fromBytes($colorsIdMarker),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),

			'drive_time_mm' => $d['drive_time_mm'],
			'drive_time_ss' => $d['drive_time_ss'],

			'is_operation_only_stop' => $d['is_operation_only_stop'],
			'is_pass' => $d['is_pass'],
			'has_bracket' => $d['has_bracket'],
			'is_last_stop' => $d['is_last_stop'],

			'arrive_time_hh' => $d['arrive_time_hh'],
			'arrive_time_mm' => $d['arrive_time_mm'],
			'arrive_time_ss' => $d['arrive_time_ss'],

			'departure_time_hh' => $d['departure_time_hh'],
			'departure_time_mm' => $d['departure_time_mm'],
			'departure_time_ss' => $d['departure_time_ss'],

			'run_in_limit' => $d['run_in_limit'],
			'run_out_limit' => $d['run_out_limit'],

			'remarks' => $d['remarks'],

			'arrive_str' => $d['arrive_str'],
			'departure_str' => $d['departure_str'],

			'marker_text' => $d['marker_text'],

			'work_type' => WorkAtStationType::fromOrNull($d['work_type']),
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		timetable_rows_id,
		trains_id,
		stations_id,
		station_tracks_id,
		colors_id,
		description,
		owner,

		drive_time_mm,
		drive_time_ss,

		is_operation_only_stop,
		is_pass,
		has_bracket,
		is_last_stop,

		arrive_time_hh,
		arrive_time_mm,
		arrive_time_ss,

		departure_time_hh,
		departure_time_mm,
		departure_time_ss,

		run_in_limit,
		run_out_limit,

		remarks,

		arrive_str,
		departure_str,

		marker_text,

		work_type
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:timetable_rows_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:stations_id_{$i},
				:station_tracks_id_{$i},
				:colors_id_{$i},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},

				:drive_time_mm_{$i},
				:drive_time_ss_{$i},

				:is_operation_only_stop_{$i},
				:is_pass_{$i},
				:has_bracket_{$i},
				:is_last_stop_{$i},

				:arrive_time_hh_{$i},
				:arrive_time_mm_{$i},
				:arrive_time_ss_{$i},

				:departure_time_hh_{$i},
				:departure_time_mm_{$i},
				:departure_time_ss_{$i},

				:run_in_limit_{$i},
				:run_out_limit_{$i},

				:remarks_{$i},

				:arrive_str_{$i},
				:departure_str_{$i},

				:marker_text_{$i},

				:work_type_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":timetable_rows_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":stations_id_$i", $d->stations_id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":station_tracks_id_$i", $d->station_tracks_id?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":colors_id_$i", $d->colors_id_marker?->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);

		$query->bindValue(":drive_time_mm_$i", $d->drive_time_mm, PDO::PARAM_INT);
		$query->bindValue(":drive_time_ss_$i", $d->drive_time_ss, PDO::PARAM_INT);

		$query->bindValue(":is_operation_only_stop_$i", $d->is_operation_only_stop, PDO::PARAM_BOOL);
		$query->bindValue(":is_pass_$i", $d->is_pass, PDO::PARAM_BOOL);
		$query->bindValue(":has_bracket_$i", $d->has_bracket, PDO::PARAM_BOOL);
		$query->bindValue(":is_last_stop_$i", $d->is_last_stop, PDO::PARAM_BOOL);

		$query->bindValue(":arrive_time_hh_$i", $d->arrive_time_hh, PDO::PARAM_INT);
		$query->bindValue(":arrive_time_mm_$i", $d->arrive_time_mm, PDO::PARAM_INT);
		$query->bindValue(":arrive_time_ss_$i", $d->arrive_time_ss, PDO::PARAM_INT);

		$query->bindValue(":departure_time_hh_$i", $d->departure_time_hh, PDO::PARAM_INT);
		$query->bindValue(":departure_time_mm_$i", $d->departure_time_mm, PDO::PARAM_INT);
		$query->bindValue(":departure_time_ss_$i", $d->departure_time_ss, PDO::PARAM_INT);

		$query->bindValue(":run_in_limit_$i", $d->run_in_limit, PDO::PARAM_INT);
		$query->bindValue(":run_out_limit_$i", $d->run_out_limit, PDO::PARAM_INT);

		$query->bindValue(":remarks_$i", $d->remarks, PDO::PARAM_STR);

		$query->bindValue(":arrive_str_$i", $d->arrive_str, PDO::PARAM_STR);
		$query->bindValue(":departure_str_$i", $d->departure_str, PDO::PARAM_STR);

		$query->bindValue(":marker_text_$i", $d->marker_text, PDO::PARAM_STR);

		$query->bindValue(":work_type_$i", $d->work_type?->value, PDO::PARAM_STR);
	}

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		if ($key === 'colors_id_marker') {
			return "colors_id_marker = :{$key}";
		}
		return "{$key} = :{$key}";
	}

	/**
	 * @param array<UuidInterface> $parentIdList
	 * @param array<string, array<TRViSJsonTimetableRow>> $dst
	 * @return RetValueOrError<array<UuidInterface>>
	 */
	public function dump(
		array $parentIdList,
		array &$dst,
	): RetValueOrError {
		$this->logger->debug(
			'Dumping timetable rows... {parentIdList}',
			[
				'parentIdList' => $parentIdList,
			],
		);

		$parentIdCount = count($parentIdList);
		$parentIdListPlaceholder = implode(', ', array_fill(0, $parentIdCount, '?'));
		try
		{
			// station_tracksとのJOINは、本当はstations_idも条件として加えるべきである。
			// しかし、実装上の都合で同じWorkGroupの他の駅に属するstation_tracksも登録できてしまう。
			// そのため、stations_idでの絞り込みは行わない。
			$query = $this->db->prepare(<<<SQL
				SELECT
					HEX(timetable_rows.trains_id) AS parent_id,
					timetable_rows.timetable_rows_id AS timetable_rows_id,
					stations.name AS StationName,
					stations.location_km * 1000 AS Location_m,
					ST_X(stations.location_lonlat) AS Longitude_deg,
					ST_Y(stations.location_lonlat) AS Latitude_deg,
					stations.on_station_detect_radius_m AS OnStationDetectRadius_m,
					stations.name AS FullName,
					stations.record_type AS RecordType,
					station_tracks.name AS TrackName,

					timetable_rows.drive_time_mm AS DriveTime_MM,
					timetable_rows.drive_time_ss AS DriveTime_SS,

					timetable_rows.is_operation_only_stop AS IsOperationOnlyStop,
					timetable_rows.is_pass AS IsPass,
					timetable_rows.has_bracket AS HasBracket,
					timetable_rows.is_last_stop AS IsLastStop,

					IF(
							timetable_rows.arrive_time_hh IS NULL
						AND
							timetable_rows.arrive_time_mm IS NULL
						AND
							timetable_rows.arrive_time_ss IS NULL
						,
						timetable_rows.arrive_str,
						CONCAT(
							IFNULL(timetable_rows.arrive_time_hh, ''),
							':',
							IFNULL(timetable_rows.arrive_time_mm, ''),
							':',
							IFNULL(timetable_rows.arrive_time_ss, '')
						)
					) AS Arrive,
					IF(
							timetable_rows.departure_time_hh IS NULL
						AND
							timetable_rows.departure_time_mm IS NULL
						AND
							timetable_rows.departure_time_ss IS NULL
						,
						timetable_rows.departure_str,
						CONCAT(
							IFNULL(timetable_rows.departure_time_hh, ''),
							':',
							IFNULL(timetable_rows.departure_time_mm, ''),
							':',
							IFNULL(timetable_rows.departure_time_ss, '')
						)
					) AS Departure,

					timetable_rows.run_in_limit AS RunInLimit,
					timetable_rows.run_out_limit AS RunOutLimit,

					timetable_rows.remarks AS Remarks,

					IF(
						timetable_rows.colors_id IS NULL,
						NULL,
						CONCAT(
							LPAD(HEX(colors.red_8bit), 2, '0'),
							LPAD(HEX(colors.green_8bit), 2, '0'),
							LPAD(HEX(colors.blue_8bit), 2, '0')
						)
					) AS MarkerColor,
					timetable_rows.marker_text AS MarkerText,

					timetable_rows.work_type AS WorkType
				FROM
					timetable_rows
				JOIN
					stations
				USING
					(stations_id)
				LEFT JOIN
					station_tracks
				USING
					(station_tracks_id)
				LEFT JOIN
					colors
				USING
					(colors_id)
				WHERE
					{$this->parentTableName}_id IN ($parentIdListPlaceholder)
				ORDER BY
					Location_m ASC
				SQL
			);
			for ($i = 0; $i < $parentIdCount; ++$i) {
				$query->bindValue($i + 1, $parentIdList[$i]->getBytes(), PDO::PARAM_STR);
			}
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'selected timetable {rowCount} rows.',
				[
					'rowCount' => $rowCount,
				],
			);
			if ($rowCount === 0) {
				return RetValueOrError::withValue([]);
			}

			$rowCountPerParent = [];
			$timetableRowsIdList = [];
			$timetableRowsIdListIndex = 0;
			while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$parentId = $row['parent_id'];
				if (!array_key_exists($parentId, $rowCountPerParent)) {
					$rowCountPerParent[$parentId] = 0;
				}
				$dst[$parentId][$rowCountPerParent[$parentId]++] = self::fetchResultRowToTrvisJsonData($row);
				$timetableRowsIdList[$timetableRowsIdListIndex++] = Uuid::fromBytes($row['timetable_rows_id']);
			}

			return RetValueOrError::withValue($timetableRowsIdList);
		}
		catch (\PDOException $e)
		{
			$this->logger->error(
				'Failed to dump timetable rows. {exception}',
				[
					'exception' => $e,
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				'Failed to execute SQL - ' . $e->getCode(),
			);
		}
	}

	/**
	 * @param array<string, mixed> $kvpList
	 */
	private static function fetchResultRowToTrvisJsonData(
		array $kvpList,
	): TRViSJsonTimetableRow {
		$d = new TRViSJsonTimetableRow();
		$d->setData([
			'StationName' => $kvpList['StationName'],
			'Location_m' => $kvpList['Location_m'],
			'Longitude_deg' => Utils::floatvalOrNull($kvpList['Longitude_deg']),
			'Latitude_deg' => Utils::floatvalOrNull($kvpList['Latitude_deg']),
			'OnStationDetectRadius_m' => Utils::floatvalOrNull($kvpList['OnStationDetectRadius_m']),
			'FullName' => $kvpList['FullName'],
			'RecordType' => $kvpList['RecordType'],
			'TrackName' => $kvpList['TrackName'],

			'DriveTime_MM' => $kvpList['DriveTime_MM'],
			'DriveTime_SS' => $kvpList['DriveTime_SS'],

			'IsOperationOnlyStop' => $kvpList['IsOperationOnlyStop'],
			'IsPass' => $kvpList['IsPass'],
			'HasBracket' => $kvpList['HasBracket'],
			'IsLastStop' => $kvpList['IsLastStop'],

			'Arrive' => $kvpList['Arrive'],
			'Departure' => $kvpList['Departure'],

			'RunInLimit' => $kvpList['RunInLimit'],
			'RunOutLimit' => $kvpList['RunOutLimit'],

			'Remarks' => $kvpList['Remarks'],

			'MarkerColor' => $kvpList['MarkerColor'],
			'MarkerText' => $kvpList['MarkerText'],

			'WorkType' => $kvpList['WorkType'],
		]);
		return $d;
	}
}
