<?php

namespace dev_t0r\trvis_backend\repo;

use dev_t0r\trvis_backend\model\StationOnLine;
use dev_t0r\trvis_backend\model\StationOnLineLocationLonlat;
use dev_t0r\trvis_backend\Utils;
use PDO;
use PDOStatement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * stations_on_line。Line直下 (parentId = lineId = project_lines_id)。
 * projects_id は親Lineから導出 (INSERT時にサブクエリで埋める)。
 * API `lines_id` ↔ DB `project_lines_id`。
 * description列はAPIに無いのでINSERTから除外 (DB default '')。
 *
 * @extends MyProjectRootedRepoBase<StationOnLine>
 */
final class StationsOnLineRepo extends MyProjectRootedRepoBase
{
	public function __construct(
		PDO $db,
		\Psr\Log\LoggerInterface $logger,
	) {
		parent::__construct(
			db: $db,
			logger: $logger,
			TABLE_NAME: 'stations_on_line',
			parentTableNameList: ['project_lines'],
			SQL_SELECT_COLUMNS: self::SQL_SELECT_COLUMNS,
			SQL_INSERT_COLUMNS: self::SQL_INSERT_COLUMNS,
		);
	}

	private const SQL_SELECT_COLUMNS = <<<SQL
		stations_on_line_id,
		projects_id,
		project_lines_id AS lines_id,
		project_stations_id,
		created_at,
		location_m,
		ST_X(location_lonlat) AS location_lon,
		ST_Y(location_lonlat) AS location_lat,
		track_hidden_by_default

	SQL;

	protected function _fetchResultToObj(
		mixed $d,
	): mixed {
		$result = new StationOnLine();
		$lonlat = null;
		if (!is_null($d['location_lon']) && !is_null($d['location_lat'])) {
			$lonlat = new StationOnLineLocationLonlat();
			$lonlat->setData([
				'longitude' => floatval($d['location_lon']),
				'latitude' => floatval($d['location_lat']),
			]);
		}
		$result->setData([
			'stations_on_line_id' => Uuid::fromBytes($d['stations_on_line_id']),
			'projects_id' => Uuid::fromBytes($d['projects_id']),
			'lines_id' => Uuid::fromBytes($d['lines_id']),
			'project_stations_id' => Uuid::fromBytes($d['project_stations_id']),
			'created_at' => Utils::dbDateStrToDateTime($d['created_at']),
			'location_m' => floatval($d['location_m']),
			'location_lonlat' => $lonlat,
			'track_hidden_by_default' => boolval($d['track_hidden_by_default']),
		]);
		return $result;
	}

	private const SQL_INSERT_COLUMNS = <<<SQL
	(
		stations_on_line_id,
		projects_id,
		project_lines_id,
		project_stations_id,
		owner,
		location_m,
		location_lonlat,
		track_hidden_by_default
	)
	SQL;
	protected function _genInsertValuesQuerySegment(
		int $i
	): string {
		// projects_id は親Line (project_lines) から導出
		return <<<SQL
			(
				:stations_on_line_id_{$i},
				(SELECT pl.projects_id FROM project_lines pl WHERE pl.project_lines_id = {$this->PLACEHOLDER_PARENT_ID}),
				{$this->PLACEHOLDER_PARENT_ID},
				:project_stations_id_{$i},
				{$this->PLACEHOLDER_OWNER},
				:location_m_{$i},
				ST_PointFromText(:location_lonlat_{$i}),
				:track_hidden_by_default_{$i}
			)
		SQL;
	}
	protected function _setInsertValues(
		PDOStatement $query,
		int $i,
		UuidInterface $id,
		mixed $d,
	) {
		$latlon = null;
		if (!is_null($d->location_lonlat)) {
			$latlon = "POINT({$d->location_lonlat->longitude} {$d->location_lonlat->latitude})";
		}
		$query->bindValue(":stations_on_line_id_$i", $id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":project_stations_id_$i", $d->project_stations_id->getBytes(), PDO::PARAM_STR);
		$query->bindValue(":location_m_$i", $d->location_m, PDO::PARAM_STR);
		$query->bindValue(":location_lonlat_$i", $latlon, PDO::PARAM_STR);
		$query->bindValue(":track_hidden_by_default_$i", $d->track_hidden_by_default ?? false, PDO::PARAM_BOOL);
	}

	protected function _keyToUpdateQuerySetLine(
		string $key,
	): string {
		if ($key === 'location_lonlat') {
			return "{$key} = ST_PointFromText(:{$key})";
		}
		return parent::_keyToUpdateQuerySetLine($key);
	}
	protected function _kvpToValueToBind(
		string $key,
		mixed $value,
	): mixed {
		if ($key === 'location_lonlat') {
			return "POINT({$value->longitude} {$value->latitude})";
		}
		return parent::_kvpToValueToBind($key, $value);
	}
}
