<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\Station;
use dev_t0r\trvis_backend\model\StationLocationLonlat;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class StationsRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		stations_id,
		work_groups_id,
		description,
		created_at,
		name,
		location_km,
		ST_X(location_lonlat) AS location_lon,
		ST_Y(location_lonlat) AS location_lat,
		on_station_detect_radius_m,
		record_type

	SQL;

	private static function _fetchResultToObj(
		mixed $d,
	): Station {
		$result = new Station();
		$lonlat = null;
		if (!is_null($d['location_lon']) && !is_null($d['location_lat'])) {
			$lonlat = new StationLocationLonlat();
			$lonlat->setData([
				'longitude' => $d['location_lon'],
				'latitude' => $d['location_lat'],
			]);
		}
		$result->setData([
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'work_groups_id' => Uuid::fromBytes($d['work_groups_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'location_km' => $d['location_km'],
			'location_lonlat' => $lonlat,
			'on_station_detect_radius_m' => $d['on_station_detect_radius_m'],
			'record_type' => $d['record_type'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		stations_id,
		work_groups_id,
		description,
		owner,
		name,
		location_km,
		location_lonlat,
		on_station_detect_radius_m,
		record_type
	)
	SQL;
	static function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:stations_id_{$i},
				:work_groups_id,
				:description_{$i},
				:owner,
				:name_{$i},
				:location_km_{$i},
				ST_PointFromText(:location_lonlat_{$i}),
				:on_station_detect_radius_m_{$i},
				:record_type_{$i}
			)
		SQL;
	}
	static function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $stationsId,
		Station $station,
	) {
		$latlon = null;
		if (!is_null($station->location_lonlat)) {
			$lon = $station->location_lonlat->longitude;
			$lat = $station->location_lonlat->latitude;
			$latlon = "POINT($lon $lat)";
		}
		$query->bindValue(":stations_id_$i", $stationsId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $station->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $station->name, PDO::PARAM_STR);
		$query->bindValue(":location_km_$i", $station->location_km, PDO::PARAM_STR);
		$query->bindValue(":location_lonlat_$i", $latlon, PDO::PARAM_STR);
		$query->bindValue(":on_station_detect_radius_m_$i", $station->on_station_detect_radius_m, PDO::PARAM_STR);
		$query->bindValue(":record_type_$i", $station->record_type->value, PDO::PARAM_INT);
	}

		/**
	 * @return RetValueOrError<Station>
	 */
	public function selectOne(
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne stationsId: {stationsId}',
			[
				'stationsId' => $stationsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS
				.
				<<<SQL
				FROM
					stations
				WHERE
					stations_id = :stations_id
				AND
					deleted_at IS NULL
				SQL
			);

			$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne({stationsId}) - rowCount is 0',
					[
						'stationsId' => $stationsId,
					],
				);
				return Utils::errStationNotFound();
			}

			return RetValueOrError::withValue(self::_fetchResultToObj($result));
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<UuidInterface>
	 */
	public function selectWorkGroupsId(
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId stationsId: {stationsId}',
			[
				'stationsId' => $stationsId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					work_groups_id
				FROM
					stations
				WHERE
					stations_id = :stations_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return Utils::errStationNotFound();
			}

			$resultId = $result['work_groups_id'];
			return RetValueOrError::withValue(Uuid::fromBytes($resultId));
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<array<Station>>
	 */
	public function selectPage(
		UuidInterface $workGroupsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList workGroupsId: {workGroupsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'workGroupsId' => $workGroupsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$hasTopId = !is_null($topId);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					stations
				WHERE
					work_groups_id = :work_groups_id
				AND
					deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : ' AND stations_id <= :top_id ')
				.
				<<<SQL
				ORDER BY
					stations_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
			if ($hasTopId) {
				$query->bindValue(':top_id', $topId);
			}
			$query->bindValue(':perPage', $perPage, PDO::PARAM_INT);
			$query->bindValue(':offset', ($pageFrom1 - 1) * $perPage, PDO::PARAM_INT);

			$query->execute();
			$this->logger->debug(
				'rorCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			$result = $query->fetchAll(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectList - rowCount is 0');
				return Utils::errStationNotFound();
			}

			$stations = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($stations);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<array<Station>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $stationsIdList */
		array $stationsIdList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList stationsIdList: {stationsIdList}",
			[
				"stationsIdList" => $stationsIdList,
			],
		);

		$stationsIdListCount = count($stationsIdList);
		$placeholders = implode(',', array_map(
			fn($i) => ":stations_id_$i",
			range(0, $stationsIdListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					stations
				WHERE
					stations_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $stationsIdListCount; $i++) {
				$query->bindValue(":stations_id_$i", $stationsIdList[$i]->getBytes(), PDO::PARAM_STR);
			}

			$query->execute();
			$this->logger->debug(
				'rorCount: {rowCount}',
				[
					'rowCount' => $query->rowCount(),
				],
			);
			$result = $query->fetchAll(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectList - rowCount is 0');
				return Utils::errStationNotFound();
			}

			$stations = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($stations);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function insertList(
		UuidInterface $workGroupsId,
		string $ownerUserId,
		/** @param array<UuidInterface> $stationsIdList */
		array $stationsIdList,
		/** @param array<Station> $stations */
		array $stations,
	): RetValueOrError {
		$this->logger->debug(
			'insertList workGroupsId: {workGroupsId}, stations: {stations}',
			[
				'workGroupsId' => $workGroupsId,
				'stations' => $stations,
			],
		);

		try
		{
			$stationsCount = count($stations);
			$query = $this->db->prepare(
				'INSERT INTO stations'
				.
				self::SQL_INSERT_COLUMNS
				.
				' VALUES '
				.
				implode(',', array_map(
					fn($i) => self::_genInsertValuesQuerySegment($i),
					range(0, $stationsCount - 1),
				))
			);
			$query->bindValue(':work_groups_id', $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $stationsCount; $i++) {
				self::_setInsertValues($query, $i, $stationsIdList[$i], $stations[$i]);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				'insertList - rowCount: {rowCount}',
				[
					'rowCount' => $rowCount,
				],
			);
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	/**
	 * @return RetValueOrError<null>
	 */
	public function update(
		UuidInterface $stationsId,
		array $stationsProps,
	): RetValueOrError {
		$this->logger->debug(
			'updateList stationsId: {stationsId}, stations: {stations}',
			[
				'stationsId' => $stationsId,
				'stations' => $stationsProps,
			],
		);

		if (count($stationsProps) === 0) {
			return RetValueOrError::withValue(null);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE stations SET "
				.
				implode(',', array_map(
					fn($key) => ($key === 'location_lonlat'
						? "{$key} = ST_PointFromText(:{$key})"
						: "{$key} = :{$key}"
					), array_keys($stationsProps)))
				.
				" WHERE stations_id = :stations_id AND deleted_at IS NULL"
			);
			$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
			foreach ($stationsProps as $key => $value) {
				$paramType = PDO::PARAM_STR;
				if ($key === 'record_type') {
					$paramType = PDO::PARAM_INT;
				}
				if ($key === 'location_lonlat') {
					$value = "POINT({$value->longitude} {$value->latitude})";
					$this->logger->debug(
						"updateList - location_lonlat: {location_lonlat}",
						[
							"location_lonlat" => $value,
						],
					);
				}
				$query->bindValue(":{$key}", $value, $paramType);
			}

			$query->execute();
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	public function deleteOne(
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne stationsId: {stationsId}",
			[
				"stationsId" => $stationsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					stations
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					stations_id = :stations_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":stations_id", $stationsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteByWorkGroupsId - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"Work not found ({stationsId})",
					[
						"stationsId" => $stationsId,
					],
				);
				return Utils::errStationNotFound();
			}
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}

	public function deleteByWorkGroupsId(
		UuidInterface $workGroupsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByWorkGroupsId workGroupsId: {workGroupsId}",
			[
				"workGroupsId" => $workGroupsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					stations
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					work_groups_id = :work_groups_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":work_groups_id", $workGroupsId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$this->logger->debug(
				"deleteByWorkGroupsId - rowCount: {rowCount}",
				[
					"rowCount" => $query->rowCount(),
				],
			);
			return RetValueOrError::withValue(null);
		}
		catch (\PDOException $ex)
		{
			$errCode = $ex->getCode();

			$this->logger->error(
				"Failed to execute SQL ({errorCode} -> {errorInfo})",
				[
					"errorCode" => $errCode,
					"errorInfo" => $ex->getMessage(),
				],
			);
			return RetValueOrError::withError(
				Constants::HTTP_INTERNAL_SERVER_ERROR,
				"Failed to execute SQL - " . $errCode,
			);
		}
	}
}
