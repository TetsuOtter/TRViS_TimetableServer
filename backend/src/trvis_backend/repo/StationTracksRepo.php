<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\Constants;
use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\RetValueOrError;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class StationTracksRepo
{
	public function __construct(
		private readonly PDO $db,
		private readonly LoggerInterface $logger,
	) {
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		station_tracks.station_tracks_id AS station_tracks_id,
		station_tracks.stations_id AS stations_id,
		station_tracks.description AS description,
		station_tracks.created_at AS created_at,
		station_tracks.name AS name,
		station_tracks.run_in_limit AS run_in_limit,
		station_tracks.run_out_limit AS run_out_limit

	SQL;

	private static function _fetchResultToObj(
		mixed $d,
	): StationTrack {
		$result = new StationTrack();
		$result->setData([
			'station_tracks_id' => Uuid::fromBytes($d['station_tracks_id']),
			'stations_id' => Uuid::fromBytes($d['stations_id']),
			'description' => $d['description'],
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'name' => $d['name'],
			'run_in_limit' => $d['run_in_limit'],
			'run_out_limit' => $d['run_out_limit'],
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		station_tracks_id,
		stations_id,
		description,
		owner,
		name,
		run_in_limit,
		run_out_limit
	)
	SQL;
	static function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:station_tracks_id_{$i},
				:stations_id,
				:description_{$i},
				:owner,
				:name_{$i},
				:run_in_limit_{$i},
				:run_out_limit_{$i}
			)
		SQL;
	}
	static function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $stationTracksId,
		StationTrack $stationTrack,
	) {
		$query->bindValue(":station_tracks_id_$i", $stationTracksId->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $stationTrack->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $stationTrack->name, PDO::PARAM_STR);
		$query->bindValue(":run_in_limit_$i", $stationTrack->run_in_limit, PDO::PARAM_INT);
		$query->bindValue(":run_out_limit_$i", $stationTrack->run_out_limit, PDO::PARAM_INT);
	}

	/**
	 * @return RetValueOrError<StationTrack>
	 */
	public function selectOne(
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			'selectOne stationTracksId: {stationTracksId}',
			[
				'stationTracksId' => $stationTracksId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
					FROM station_tracks
				WHERE
					station_tracks.station_tracks_id = :station_tracks_id
				AND
					station_tracks.deleted_at IS NULL
				SQL
			);

			$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning(
					'selectOne({stationTracksId}) - rowCount is 0',
					[
						'stationTracksId' => $stationTracksId,
					],
				);
				return Utils::errStationTrackNotFound();
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
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			'selectWorkGroupsId stationTracksId: {stationTracksId}',
			[
				'stationTracksId' => $stationTracksId,
			],
		);

		try
		{
			$query = $this->db->prepare(
				<<<SQL
				SELECT
					stations.work_groups_id
				FROM
					stations
				JOIN
					station_tracks
				USING
					(stations_id)
				WHERE
					station_tracks.station_tracks_id = :station_tracks_id
				AND
					stations.deleted_at IS NULL
				AND
					station_tracks.deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);

			$query->execute();
			$result = $query->fetch(PDO::FETCH_ASSOC);
			if ($result === false) {
				$this->logger->warning('selectWorkGroupsId - rowCount is 0');
				return Utils::errStationTrackNotFound();
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
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function selectPage(
		UuidInterface $stationsId,
		int $pageFrom1,
		int $perPage,
		?UuidInterface $topId,
	): RetValueOrError {
		$this->logger->debug(
			'selectList stationsId: {stationsId}, pageFrom1: {pageFrom1}, perPage: {perPage}, topId: {topId}',
			[
				'stationsId' => $stationsId,
				'pageFrom1' => $pageFrom1,
				'perPage' => $perPage,
				'topId' => $topId,
			],
		);

		$hasTopId = !is_null($topId);

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS
				.
				<<<SQL
				FROM
					station_tracks
				WHERE
					station_tracks.stations_id = :stations_id
				AND
					station_tracks.deleted_at IS NULL
				SQL
				.
				(!$hasTopId ? ' ' : ' AND station_tracks.station_tracks_id <= :top_id ')
				.
				<<<SQL
				ORDER BY
					station_tracks_id DESC
				LIMIT
					:perPage
				OFFSET
					:offset
				SQL
			);

			$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
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
				return Utils::errStationTrackNotFound();
			}

			$stationTracks = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($stationTracks);
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
	 * @return RetValueOrError<array<StationTrack>>
	 */
	public function selectList(
		/** @param array<UuidInterface> $stationTracksIdList */
		array $stationTracksIdList,
	): RetValueOrError {
		$this->logger->debug(
			"selectList stationTracksIdList: {stationTracksIdList}",
			[
				"stationTracksIdList" => $stationTracksIdList,
			],
		);

		$stationTracksIdListCount = count($stationTracksIdList);
		$placeholders = implode(',', array_map(
			fn($i) => ":station_tracks_id_$i",
			range(0, $stationTracksIdListCount - 1),
		));

		try
		{
			$query = $this->db->prepare(
				'SELECT ' . self::SQL_SELECT_COLUMNS . <<<SQL
				FROM
					station_tracks
				WHERE
					station_tracks_id IN ($placeholders)
				SQL
			);

			for ($i = 0; $i < $stationTracksIdListCount; $i++) {
				$query->bindValue(":station_tracks_id_$i", $stationTracksIdList[$i]->getBytes(), PDO::PARAM_STR);
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
				return Utils::errStationTrackNotFound();
			}

			$stationTracks = array_map(
				fn($data) => self::_fetchResultToObj($data),
				$result,
			);

			return RetValueOrError::withValue($stationTracks);
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
		UuidInterface $stationsId,
		string $ownerUserId,
		/** @param array<UuidInterface> $stationTracksIdList */
		array $stationTracksIdList,
		/** @param array<StationTrack> $stationTracks */
		array $stationTracks,
	): RetValueOrError {
		$this->logger->debug(
			'insertList stationsId: {stationsId}, stationTracks: {stationTracks}',
			[
				'stationsId' => $stationsId,
				'stationTracks' => $stationTracks,
			],
		);

		try
		{
			$stationTracksCount = count($stationTracks);
			$query = $this->db->prepare(
				'INSERT INTO station_tracks'
				.
				self::SQL_INSERT_COLUMNS
				.
				' VALUES '
				.
				implode(',', array_map(
					fn($i) => self::_genInsertValuesQuerySegment($i),
					range(0, $stationTracksCount - 1),
				))
			);
			$query->bindValue(':stations_id', $stationsId->getBytes(), PDO::PARAM_STR);
			$query->bindValue(':owner', $ownerUserId, PDO::PARAM_STR);
			for ($i = 0; $i < $stationTracksCount; $i++) {
				self::_setInsertValues($query, $i, $stationTracksIdList[$i], $stationTracks[$i]);
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
		UuidInterface $stationTracksId,
		array $stationTracksProps,
	): RetValueOrError {
		$this->logger->debug(
			'updateList stationTracksId: {stationTracksId}, stationTracks: {stationTracks}',
			[
				'stationTracksId' => $stationTracksId,
				'stationTracks' => $stationTracksProps,
			],
		);

		if (count($stationTracksProps) === 0) {
			return RetValueOrError::withValue(null);
		}

		try
		{
			$query = $this->db->prepare(
				"UPDATE station_tracks SET "
				.
				implode(',', array_map(fn($key) => "{$key} = :{$key}", array_keys($stationTracksProps)))
				.
				" WHERE station_tracks_id = :station_tracks_id AND deleted_at IS NULL"
			);
			$query->bindValue(':station_tracks_id', $stationTracksId->getBytes(), PDO::PARAM_STR);
			foreach ($stationTracksProps as $key => $value) {
				$paramType = PDO::PARAM_STR;
				if ($key === 'run_in_limit' || $key === 'run_out_limit') {
					$paramType = PDO::PARAM_INT;
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
		UuidInterface $stationTracksId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteOne stationTracksId: {stationTracksId}",
			[
				"stationTracksId" => $stationTracksId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					station_tracks
				SET
					deleted_at = CURRENT_TIMESTAMP()
				WHERE
					station_tracks_id = :station_tracks_id
				AND
					deleted_at IS NULL
				;
				SQL
			);

			$query->bindValue(":station_tracks_id", $stationTracksId->getBytes(), PDO::PARAM_STR);
			$query->execute();
			$rowCount = $query->rowCount();
			$this->logger->debug(
				"deleteOne - rowCount: {rowCount}",
				[
					"rowCount" => $rowCount,
				],
			);
			if ($rowCount === 0) {
				$this->logger->warning(
					"StationTrack not found ({stationTracksId})",
					[
						"stationTracksId" => $stationTracksId,
					],
				);
				return Utils::errStationTrackNotFound();
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

	public function deleteByStationsId(
		UuidInterface $stationsId,
	): RetValueOrError {
		$this->logger->debug(
			"deleteByStationsId stationsId: {stationsId}",
			[
				"stationsId" => $stationsId,
			],
		);

		try
		{
			$query = $this->db->prepare(<<<SQL
				UPDATE
					station_tracks
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
			$this->logger->debug(
				"deleteByStationsId - rowCount: {rowCount}",
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
					station_tracks
				JOIN
					stations
				USING
					(stations_id)
				SET
					station_tracks.deleted_at = CURRENT_TIMESTAMP()
				WHERE
					stations.work_groups_id = :work_groups_id
				AND
					station_tracks.deleted_at IS NULL
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
