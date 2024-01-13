<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\StationTrack;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends MyRepoBase<StationTrack>
 */
final class StationTracksRepo extends MyRepoBase
{
	public function __construct(
		PDO $db,
		LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'station_tracks',
			parentTableNameList: ['stations', 'work_groups'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
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

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
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
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		return <<<SQL
			(
				:station_tracks_id_{$i},
				{$this->PLACEHOLDER_PARENT_ID},
				:description_{$i},
				{$this->PLACEHOLDER_OWNER},
				:name_{$i},
				:name_{$i},
				:run_in_limit_{$i},
				:run_out_limit_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$query->bindValue(":station_tracks_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":description_$i", $d->description, PDO::PARAM_STR);
		$query->bindValue(":name_$i", $d->name, PDO::PARAM_STR);
		$query->bindValue(":run_in_limit_$i", $d->run_in_limit, PDO::PARAM_INT);
		$query->bindValue(":run_out_limit_$i", $d->run_out_limit, PDO::PARAM_INT);
	}
}
